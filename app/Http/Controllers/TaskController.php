<?php

namespace App\Http\Controllers;

use App\Enums\TaskSource;
use App\Http\Requests\MoveTaskRequest;
use App\Http\Requests\SetTaskDueDateRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
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
            ->with('project:id,name,color')
            ->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->orderBy('due_date')
            ->get();

        $due = $request->user()->tasks()
            ->with('project:id,name,color')
            ->whereDate('due_date', $today)
            ->orderBy('completed_at')
            ->get();

        return Inertia::render('Vandaag', [
            'date' => $today,
            'overdue' => $overdue,
            'today' => $due,
        ]);
    }

    /**
     * The Inbox view: unassigned tasks (project_id is null).
     */
    public function inbox(Request $request): Response
    {
        Gate::authorize('viewAny', Task::class);

        $tasks = $request->user()->tasks()
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

        $request->user()->tasks()->create([
            ...$request->validated(),
            'source' => TaskSource::Manual,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Taak toegevoegd.']);

        return back();
    }

    /**
     * Update the given task.
     */
    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $task->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Taak bijgewerkt.']);

        return back();
    }

    /**
     * Move the task to a project, or back to the Inbox (null project_id).
     */
    public function move(MoveTaskRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $task->update(['project_id' => $request->validated('project_id')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Taak verplaatst.']);

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
     * Delete the given task.
     */
    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);

        $task->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Taak verwijderd.']);

        return back();
    }
}
