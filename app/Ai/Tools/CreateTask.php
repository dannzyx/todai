<?php

namespace App\Ai\Tools;

use App\Enums\TaskSource;
use App\Jobs\ClassifyTaskProject;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateTask implements Tool
{
    /**
     * @param  Collection<int, Task>  $createdTasks  Collector for tasks made this turn.
     */
    public function __construct(
        protected User $user,
        protected Collection $createdTasks,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Create exactly one task for the user. Call this multiple times if '
            .'there are multiple tasks. Only fill project when the user clearly '
            .'names an existing project.';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'description' => $schema->string()->nullable(),
            'due_date' => $schema->string()->nullable(), // YYYY-MM-DD
            'project' => $schema->string()->nullable(),  // exact name of an existing project
        ];
    }

    /**
     * Execute the tool: create the task for the current (injected) user.
     */
    public function handle(Request $request): Stringable|string
    {
        $data = $request->all();
        $title = trim((string) ($data['title'] ?? ''));

        if ($title === '') {
            return 'No task created: a title was missing.';
        }

        $project = $this->resolveProject($data['project'] ?? null);
        $dueDate = $this->resolveDueDate($data['due_date'] ?? null);

        $task = $this->user->tasks()->create([
            'title' => $title,
            'description' => ($data['description'] ?? null) ?: null,
            'due_date' => $dueDate,
            'project_id' => $project?->id,
            'source' => TaskSource::Chat,
        ]);

        // Unassigned chat tasks land in the Inbox and get an AI project suggestion.
        if ($task->project_id === null) {
            ClassifyTaskProject::dispatch($task);
        }

        $this->createdTasks->push($task);

        return sprintf(
            'Created task: "%s"%s%s.',
            $title,
            $dueDate ? " (due {$dueDate})" : '',
            $project ? " in project {$project->name}" : ' in the inbox',
        );
    }

    /**
     * Resolve a project name to one of the user's active projects.
     */
    protected function resolveProject(mixed $name): ?Project
    {
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        return $this->user->projects()
            ->active()
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim($name))])
            ->first();
    }

    /**
     * Validate an incoming YYYY-MM-DD date string, or return null.
     */
    protected function resolveDueDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);
        $date = \DateTime::createFromFormat('Y-m-d', $trimmed);

        return $date && $date->format('Y-m-d') === $trimmed
            ? $trimmed
            : null;
    }
}
