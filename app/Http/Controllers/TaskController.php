<?php

namespace App\Http\Controllers;

use App\Enums\TaskSource;
use App\Http\Requests\MoveTaskRequest;
use App\Http\Requests\SetTaskDueDateRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Jobs\ClassifyTaskProject;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    /**
     * The Vandaag view: overdue and due-today tasks across all projects.
     */
    public function today(Request $request): Response
    {
        Gate::authorize('viewAny', Task::class);

        $today = now()->toDateString();

        $overdue = $request->user()->tasks()
            ->with(['project:id,name,color', 'suggestedProject:id,name,color'])
            ->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->orderBy('due_date')
            ->get();

        $due = $request->user()->tasks()
            ->with(['project:id,name,color', 'suggestedProject:id,name,color'])
            ->whereDate('due_date', $today)
            ->orderBy('completed_at')
            ->get();

        // Unassigned, open tasks that aren't already on today's agenda (overdue /
        // due-today) — so quick-adds are visible without duplicating the agenda.
        $inbox = $request->user()->tasks()
            ->with('suggestedProject:id,name,color')
            ->inInbox()
            ->incomplete()
            ->where(fn ($query) => $query
                ->whereNull('due_date')
                ->orWhereDate('due_date', '>', $today))
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->latest('created_at')
            ->get();

        $projects = $request->user()->projects()
            ->active()
            ->withCount(['tasks as open_tasks_count' => fn ($query) => $query->whereNull('completed_at')])
            ->orderBy('name')
            ->get();

        return Inertia::render('Vandaag', [
            'date' => $today,
            'overdue' => $overdue,
            'today' => $due,
            'inbox' => $inbox,
            'projects' => $projects,
        ]);
    }

    /**
     * The Inbox view: unassigned tasks (project_id is null).
     */
    public function inbox(Request $request): Response
    {
        Gate::authorize('viewAny', Task::class);

        $tasks = $request->user()->tasks()
            ->with('suggestedProject:id,name,color')
            ->inInbox()
            ->orderByRaw('completed_at is null desc')
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->latest('created_at')
            ->get();

        return Inertia::render('Inbox', [
            'tasks' => $tasks,
        ]);
    }

    /**
     * Store a newly created task (quick-add or full form).
     */
    public function store(StoreTaskRequest $request): RedirectResponse
    {
        Gate::authorize('create', Task::class);

        $task = $request->user()->tasks()->create([
            ...$request->validated(),
            'source' => TaskSource::Manual,
        ]);

        // Tasks that land in the Inbox get an AI project suggestion.
        if ($task->project_id === null) {
            ClassifyTaskProject::dispatch($task);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Task added.']);

        return back();
    }

    /**
     * Update the given task.
     */
    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $task->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Task updated.']);

        return back();
    }

    /**
     * Move the task to a project, or back to the Inbox (null project_id).
     */
    public function move(MoveTaskRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $task->update(['project_id' => $request->validated('project_id')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Task moved.']);

        return back();
    }

    /**
     * Set or clear the task's due date.
     */
    public function setDueDate(SetTaskDueDateRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $task->update(['due_date' => $request->validated('due_date')]);

        return back();
    }

    /**
     * Toggle the task's completion state.
     */
    public function toggle(Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $task->update([
            'completed_at' => $task->completed_at === null ? now() : null,
        ]);

        return back();
    }

    /**
     * Re-run the AI project suggestion for an Inbox task on demand.
     */
    public function suggest(Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        if ($task->project_id === null) {
            ClassifyTaskProject::dispatch($task);
        }

        return back();
    }

    /**
     * Accept the AI suggestion: assign the task and clear the suggestion.
     */
    public function acceptSuggestion(Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        if ($task->suggested_project_id !== null) {
            $task->update([
                'project_id' => $task->suggested_project_id,
                'suggested_project_id' => null,
                'suggestion_confidence' => null,
                'suggestion_reasoning' => null,
            ]);

            Inertia::flash('toast', ['type' => 'success', 'message' => 'Task assigned.']);
        }

        return back();
    }

    /**
     * Dismiss the AI suggestion without assigning the task.
     */
    public function dismissSuggestion(Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $task->update([
            'suggested_project_id' => null,
            'suggestion_confidence' => null,
            'suggestion_reasoning' => null,
        ]);

        return back();
    }

    /**
     * Delete the given task.
     */
    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);

        $task->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Task deleted.']);

        return back();
    }
}
