<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Display the user's projects.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Project::class);

        $projects = $request->user()->projects()
            ->withCount(['tasks as open_tasks_count' => fn ($query) => $query->whereNull('completed_at')])
            ->orderBy('name')
            ->get();

        return Inertia::render('projects/Index', [
            'active' => $projects->whereNull('archived_at')->values(),
            'archived' => $projects->whereNotNull('archived_at')->values(),
        ]);
    }

    /**
     * Store a newly created project.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        Gate::authorize('create', Project::class);

        $request->user()->projects()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project aangemaakt.']);

        return to_route('projects.index');
    }

    /**
     * Display a single project.
     */
    public function show(Project $project): Response
    {
        Gate::authorize('view', $project);

        $project->loadCount(['tasks as open_tasks_count' => fn ($query) => $query->whereNull('completed_at')]);

        $tasks = $project->tasks()
            ->orderByRaw('completed_at is null desc')
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->latest('created_at')
            ->get();

        return Inertia::render('projects/Show', [
            'project' => $project,
            'tasks' => $tasks,
        ]);
    }

    /**
     * Update the given project.
     */
    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $project->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project bijgewerkt.']);

        return back();
    }

    /**
     * Archive the given project (soft archive; its tasks remain).
     */
    public function archive(Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $project->update(['archived_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project gearchiveerd.']);

        return back();
    }

    /**
     * Restore the given archived project.
     */
    public function unarchive(Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $project->update(['archived_at' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project hersteld.']);

        return back();
    }
}
