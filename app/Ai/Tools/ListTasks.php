<?php

namespace App\Ai\Tools;

use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListTasks implements Tool
{
    public function __construct(protected User $user) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Retrieve the user\'s tasks. Call this before answering any question '
            .'about what is open, overdue, due today, upcoming, or in the inbox. '
            .'Pick a scope: today (overdue + due today), overdue, due_today, '
            .'upcoming, inbox, open (all incomplete), completed, or all.';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'scope' => $schema->string()->nullable(), // today|overdue|due_today|upcoming|inbox|open|completed|all
            'project' => $schema->string()->nullable(), // exact name of an existing project
        ];
    }

    /**
     * Execute the tool: return a readable list of the matching tasks.
     */
    public function handle(Request $request): Stringable|string
    {
        $data = $request->all();
        $scope = $this->normaliseScope($data['scope'] ?? null);
        $today = now()->toDateString();

        $query = $this->user->tasks()->getQuery()->with('project:id,name');

        $this->applyScope($query, $scope, $today);

        if (($projectName = $this->resolveProjectFilter($data['project'] ?? null)) !== null) {
            $query->whereHas('project', fn (Builder $projectQuery) => $projectQuery
                ->whereRaw('lower(name) = ?', [mb_strtolower($projectName)]));
        }

        $tasks = $query
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->latest('created_at')
            ->get();

        if ($tasks->isEmpty()) {
            return "No tasks match (scope: {$scope}).";
        }

        $lines = $tasks
            ->map(fn (Task $task): string => $this->formatTask($task, $today))
            ->implode("\n");

        return "Matching tasks (scope: {$scope}), {$tasks->count()} total:\n{$lines}";
    }

    /**
     * Constrain the query to the requested scope.
     *
     * @param  Builder<Task>  $query
     */
    protected function applyScope(Builder $query, string $scope, string $today): void
    {
        match ($scope) {
            'today' => $query->incomplete()->whereNotNull('due_date')->whereDate('due_date', '<=', $today),
            'overdue' => $query->incomplete()->whereNotNull('due_date')->whereDate('due_date', '<', $today),
            'due_today' => $query->incomplete()->whereDate('due_date', $today),
            'upcoming' => $query->incomplete()->whereDate('due_date', '>', $today),
            'inbox' => $query->incomplete()->inInbox(),
            'completed' => $query->whereNotNull('completed_at'),
            'all' => null,
            default => $query->incomplete(), // 'open'
        };
    }

    /**
     * Render a single task as a readable line.
     */
    protected function formatTask(Task $task, string $today): string
    {
        $parts = ['- '.$task->title];

        if ($task->due_date !== null) {
            $due = $task->due_date->toDateString();
            $marker = match (true) {
                $task->completed_at !== null => '',
                $due < $today => ' overdue',
                $due === $today => ' today',
                default => '',
            };
            $parts[] = "(due {$due}{$marker})";
        }

        if ($task->project !== null) {
            $parts[] = "· {$task->project->name}";
        } else {
            $parts[] = '· inbox';
        }

        if ($task->completed_at !== null) {
            $parts[] = '[done]';
        }

        return implode(' ', $parts);
    }

    /**
     * Normalise an incoming scope value to a supported keyword.
     */
    protected function normaliseScope(mixed $value): string
    {
        $allowed = ['today', 'overdue', 'due_today', 'upcoming', 'inbox', 'open', 'completed', 'all'];
        $scope = is_string($value) ? mb_strtolower(trim($value)) : '';

        return in_array($scope, $allowed, true) ? $scope : 'open';
    }

    /**
     * Return a trimmed project-name filter, or null when none was given.
     */
    protected function resolveProjectFilter(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
