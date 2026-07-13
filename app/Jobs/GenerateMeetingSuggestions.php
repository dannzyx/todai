<?php

namespace App\Jobs;

use App\Ai\Agents\MeetingSuggestionAgent;
use App\Enums\MeetingStatus;
use App\Enums\SuggestionConfidence;
use App\Enums\SuggestionStatus;
use App\Models\Meeting;
use App\Models\Project;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

class GenerateMeetingSuggestions implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Meeting $meeting) {}

    /**
     * Generate staged todo suggestions and a single project suggestion from the
     * meeting's content. Nothing becomes a real task until the user accepts.
     */
    public function handle(): void
    {
        $meeting = $this->meeting->fresh();

        if ($meeting === null) {
            return;
        }

        try {
            /** @var Collection<int, Project> $projects */
            $projects = $meeting->user->projects()->active()->orderBy('name')->get();

            $response = (new MeetingSuggestionAgent($meeting->user))
                ->prompt($this->buildPrompt($meeting, $projects));

            if (! $response instanceof StructuredAgentResponse) {
                $this->fail($meeting, 'Generating suggestions failed.');

                return;
            }

            $data = $response->toArray();

            // Re-generating replaces any still-pending suggestions.
            $meeting->taskSuggestions()
                ->where('status', SuggestionStatus::Pending)
                ->delete();

            foreach (($data['tasks'] ?? []) as $task) {
                if (! is_array($task) || empty($task['title'])) {
                    continue;
                }

                $meeting->taskSuggestions()->create([
                    'title' => $task['title'],
                    'description' => $task['description'] ?? null,
                    'due_date' => $this->validateDate($task['due_date'] ?? null),
                    'status' => SuggestionStatus::Pending,
                ]);
            }

            $meeting->update([
                ...$this->resolveProjectSuggestion($data['project'] ?? [], $projects),
                'language' => $data['language'] ?? null,
                'status' => MeetingStatus::Ready,
                'processed_at' => now(),
                'error' => null,
            ]);
        } catch (Throwable $e) {
            $this->fail($meeting, $e->getMessage());
        }
    }

    /**
     * Map the agent's project block onto meeting suggestion columns.
     *
     * @param  array<string, mixed>  $project
     * @param  Collection<int, Project>  $projects
     * @return array<string, mixed>
     */
    protected function resolveProjectSuggestion(array $project, Collection $projects): array
    {
        $index = $project['existing_index'] ?? null;
        $existing = is_int($index) && $index >= 1 && $index <= $projects->count()
            ? $projects[$index - 1]
            : null;

        $newName = is_string($project['new_project_name'] ?? null)
            ? trim($project['new_project_name'])
            : '';

        // Prefer an existing project; only propose a new name when none matched.
        $hasSuggestion = $existing !== null || $newName !== '';

        return [
            'suggested_project_id' => $existing?->id,
            'suggested_project_name' => $existing === null && $newName !== '' ? $newName : null,
            'suggestion_confidence' => $hasSuggestion
                ? SuggestionConfidence::tryFrom((string) ($project['confidence'] ?? 'low'))
                : null,
            'suggestion_reasoning' => $hasSuggestion ? ($project['reasoning'] ?? null) : null,
        ];
    }

    /**
     * Mark the meeting as failed without throwing past the queue retry budget.
     */
    protected function fail(Meeting $meeting, string $error): void
    {
        $meeting->update([
            'status' => MeetingStatus::Failed,
            'error' => $error,
        ]);
    }

    /**
     * Build the suggestion prompt from the meeting's content and project list.
     *
     * @param  Collection<int, Project>  $projects
     */
    protected function buildPrompt(Meeting $meeting, Collection $projects): string
    {
        $meetingDate = $meeting->meeting_date?->toDateString() ?? 'unknown';
        $timezone = config('app.timezone');

        $list = $projects
            ->values()
            ->map(function (Project $project, int $i): string {
                $description = $project->description ? " — {$project->description}" : '';

                return sprintf('%d. %s%s', $i + 1, $project->name, $description);
            })
            ->implode("\n") ?: '(no existing projects)';

        $notes = $meeting->notes ?: '(none)';
        $actionItems = $meeting->action_items ?: '(none)';
        $summary = $meeting->summary ?: '(none)';
        $transcript = $meeting->transcript ?: '(none)';

        return <<<PROMPT
        Meeting: {$meeting->title}
        Date: {$meetingDate} (timezone {$timezone}).

        Existing projects:
        {$list}

        Manual notes:
        {$notes}

        Fireflies action items:
        {$actionItems}

        Summary:
        {$summary}

        Transcript:
        {$transcript}
        PROMPT;
    }

    /**
     * Validate an incoming YYYY-MM-DD string, or return null.
     */
    protected function validateDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);
        $date = \DateTime::createFromFormat('Y-m-d', $trimmed);

        return $date && $date->format('Y-m-d') === $trimmed ? $trimmed : null;
    }
}
