<?php

namespace App\Jobs;

use App\Ai\Agents\ProjectClassifierAgent;
use App\Enums\SuggestionConfidence;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\StructuredAgentResponse;

class ClassifyTaskProject implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Task $task) {}

    /**
     * Ask the classifier which existing project best fits the task and store
     * the suggestion. Only ever suggests — never assigns.
     */
    public function handle(): void
    {
        $task = $this->task->fresh();

        // Nothing to suggest for a task that already lives in a project.
        if ($task === null || $task->project_id !== null) {
            return;
        }

        /** @var Collection<int, Project> $projects */
        $projects = $task->user->projects()->active()->orderBy('name')->get();

        if ($projects->isEmpty()) {
            return;
        }

        $response = (new ProjectClassifierAgent($task->user))
            ->prompt($this->buildPrompt($task, $projects));

        if (! $response instanceof StructuredAgentResponse) {
            return;
        }

        $data = $response->toArray();
        $index = $data['project_index'] ?? null;
        $project = is_int($index) && $index >= 1 && $index <= $projects->count()
            ? $projects[$index - 1]
            : null;

        // Re-check the task is still unassigned before writing the suggestion.
        if ($task->fresh()?->project_id !== null) {
            return;
        }

        $task->update([
            'suggested_project_id' => $project?->id,
            'suggestion_confidence' => $project
                ? SuggestionConfidence::tryFrom((string) ($data['confidence'] ?? 'low'))
                : null,
            'suggestion_reasoning' => $project ? ($data['reasoning'] ?? null) : null,
        ]);
    }

    /**
     * Build the classification prompt: the task plus a numbered project list.
     *
     * @param  Collection<int, Project>  $projects
     */
    protected function buildPrompt(Task $task, Collection $projects): string
    {
        $list = $projects
            ->values()
            ->map(function (Project $project, int $i): string {
                $description = $project->description ? " — {$project->description}" : '';

                return sprintf('%d. %s%s', $i + 1, $project->name, $description);
            })
            ->implode("\n");

        $description = $task->description ? "\nOmschrijving: {$task->description}" : '';

        return <<<PROMPT
        Taak:
        Titel: {$task->title}{$description}

        Projecten:
        {$list}
        PROMPT;
    }
}
