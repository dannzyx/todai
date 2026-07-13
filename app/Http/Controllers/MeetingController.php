<?php

namespace App\Http\Controllers;

use App\Enums\MeetingSource;
use App\Enums\MeetingStatus;
use App\Enums\SuggestionStatus;
use App\Enums\TaskSource;
use App\Http\Requests\StoreMeetingRequest;
use App\Http\Requests\UpdateMeetingRequest;
use App\Jobs\GenerateMeetingSuggestions;
use App\Models\Meeting;
use App\Models\TaskSuggestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    /**
     * List the user's meetings, newest first.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Meeting::class);

        $meetings = $request->user()->meetings()
            ->with('project:id,name,color')
            ->withCount(['taskSuggestions as pending_suggestions_count' => function ($query) {
                $query->where('status', SuggestionStatus::Pending);
            }])
            ->orderByRaw('meeting_date is null')
            ->latest('meeting_date')
            ->latest('created_at')
            ->get();

        return Inertia::render('meetings/Index', [
            'meetings' => $meetings,
        ]);
    }

    /**
     * Show a single meeting with its content and pending suggestions.
     */
    public function show(Meeting $meeting): Response
    {
        Gate::authorize('view', $meeting);

        $meeting->load([
            'project:id,name,color',
            'suggestedProject:id,name,color',
            'taskSuggestions' => function ($query) {
                $query->where('status', SuggestionStatus::Pending)
                    ->orderByDesc('for_me')
                    ->latest('created_at');
            },
        ]);

        return Inertia::render('meetings/Show', [
            'meeting' => $meeting,
        ]);
    }

    /**
     * Store a manually created meeting.
     */
    public function store(StoreMeetingRequest $request): RedirectResponse
    {
        Gate::authorize('create', Meeting::class);

        $meeting = $request->user()->meetings()->create([
            ...$request->validated(),
            'source' => MeetingSource::Manual,
            'status' => MeetingStatus::Draft,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Meeting created.']);

        return to_route('meetings.show', $meeting);
    }

    /**
     * Update a meeting's title, date, and notes.
     */
    public function update(UpdateMeetingRequest $request, Meeting $meeting): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        $meeting->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Meeting updated.']);

        return back();
    }

    /**
     * Delete a meeting (and its suggestions).
     */
    public function destroy(Meeting $meeting): RedirectResponse
    {
        Gate::authorize('delete', $meeting);

        $meeting->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Meeting deleted.']);

        return to_route('meetings.index');
    }

    /**
     * (Re)generate the AI todo and project suggestions for a meeting.
     */
    public function generate(Meeting $meeting): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        $meeting->update(['status' => MeetingStatus::Processing]);

        GenerateMeetingSuggestions::dispatch($meeting);

        return back();
    }

    /**
     * Accept a single todo suggestion, turning it into a real task.
     */
    public function acceptSuggestion(Meeting $meeting, TaskSuggestion $taskSuggestion): RedirectResponse
    {
        Gate::authorize('update', $meeting);
        abort_unless($taskSuggestion->meeting_id === $meeting->id, 404);

        $this->acceptTaskSuggestion($meeting, $taskSuggestion);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Task added.']);

        return back();
    }

    /**
     * Accept every pending todo suggestion for the meeting at once.
     */
    public function acceptAllSuggestions(Meeting $meeting): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        $pending = $meeting->taskSuggestions()->where('status', SuggestionStatus::Pending)->get();

        foreach ($pending as $suggestion) {
            $this->acceptTaskSuggestion($meeting, $suggestion);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Added {$pending->count()} tasks."]);

        return back();
    }

    /**
     * Dismiss a single todo suggestion without creating a task.
     */
    public function dismissSuggestion(Meeting $meeting, TaskSuggestion $taskSuggestion): RedirectResponse
    {
        Gate::authorize('update', $meeting);
        abort_unless($taskSuggestion->meeting_id === $meeting->id, 404);

        $taskSuggestion->update(['status' => SuggestionStatus::Dismissed]);

        return back();
    }

    /**
     * Accept the meeting's project suggestion, resolving it to a real project.
     */
    public function acceptProject(Meeting $meeting): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        $projectId = $meeting->suggested_project_id ?? $this->resolveNewProject($meeting);

        if ($projectId !== null) {
            $meeting->update([
                'project_id' => $projectId,
                'suggested_project_id' => null,
                'suggested_project_name' => null,
                'suggestion_confidence' => null,
                'suggestion_reasoning' => null,
            ]);

            Inertia::flash('toast', ['type' => 'success', 'message' => 'Project linked.']);
        }

        return back();
    }

    /**
     * Dismiss the meeting's project suggestion without linking a project.
     */
    public function dismissProject(Meeting $meeting): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        $meeting->update([
            'suggested_project_id' => null,
            'suggested_project_name' => null,
            'suggestion_confidence' => null,
            'suggestion_reasoning' => null,
        ]);

        return back();
    }

    /**
     * Turn a pending suggestion into a task filed under the meeting's project.
     */
    protected function acceptTaskSuggestion(Meeting $meeting, TaskSuggestion $suggestion): void
    {
        if ($suggestion->status !== SuggestionStatus::Pending) {
            return;
        }

        $task = $meeting->user->tasks()->create([
            'title' => $suggestion->title,
            'description' => $suggestion->description,
            'due_date' => $suggestion->due_date,
            'project_id' => $meeting->project_id,
            'source' => $meeting->source === MeetingSource::Manual
                ? TaskSource::Manual
                : TaskSource::Fireflies,
            'meeting_id' => $meeting->id,
        ]);

        $suggestion->update([
            'status' => SuggestionStatus::Accepted,
            'accepted_task_id' => $task->id,
        ]);
    }

    /**
     * Create (or reuse) the project proposed by the AI, returning its id.
     */
    protected function resolveNewProject(Meeting $meeting): ?string
    {
        $name = trim((string) $meeting->suggested_project_name);

        if ($name === '') {
            return null;
        }

        // Don't create duplicates — reuse an existing active project by name.
        $existing = $meeting->user->projects()
            ->active()
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing !== null) {
            return $existing->id;
        }

        return $meeting->user->projects()->create(['name' => $name])->id;
    }
}
