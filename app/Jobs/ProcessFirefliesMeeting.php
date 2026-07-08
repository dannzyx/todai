<?php

namespace App\Jobs;

use App\Ai\Agents\TaskExtractorAgent;
use App\Enums\MeetingImportStatus;
use App\Enums\TaskSource;
use App\Models\MeetingImport;
use App\Services\FirefliesClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

class ProcessFirefliesMeeting implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public MeetingImport $meetingImport) {}

    /**
     * Fetch the transcript for the meeting, extract action items into Inbox tasks
     * (each with an AI project suggestion queued), and mark the import processed.
     */
    public function handle(FirefliesClient $client): void
    {
        $import = $this->meetingImport->fresh();

        if ($import === null || $import->status === MeetingImportStatus::Processed) {
            return;
        }

        $integration = $import->user?->firefliesIntegration;

        if ($integration === null) {
            $this->fail($import, 'No Fireflies connection found for this user.');

            return;
        }

        try {
            $transcript = $client->transcript($integration->api_key, $import->fireflies_meeting_id);

            if ($transcript === null) {
                $this->fail($import, 'Could not fetch the transcript from Fireflies.');

                return;
            }

            $import->update([
                'title' => $transcript['title'] ?? $import->title,
                'meeting_date' => $this->parseDate($transcript['date'] ?? null),
            ]);

            $response = (new TaskExtractorAgent)->prompt($this->buildPrompt($import, $transcript));

            if (! $response instanceof StructuredAgentResponse) {
                $this->fail($import, 'Extracting tasks failed.');

                return;
            }

            $tasks = $response->toArray()['tasks'] ?? [];

            foreach ($tasks as $task) {
                if (! is_array($task) || empty($task['title'])) {
                    continue;
                }

                $created = $import->user->tasks()->create([
                    'title' => $task['title'],
                    'description' => $task['description'] ?? null,
                    'due_date' => $this->validateDate($task['due_date'] ?? null),
                    'project_id' => null,
                    'source' => TaskSource::Fireflies,
                    'meeting_import_id' => $import->id,
                ]);

                ClassifyTaskProject::dispatch($created);
            }

            $import->update([
                'status' => MeetingImportStatus::Processed,
                'processed_at' => now(),
                'error' => null,
            ]);
        } catch (Throwable $e) {
            $this->fail($import, $e->getMessage());
        }
    }

    /**
     * Mark the import as failed without throwing past the queue retry budget.
     */
    protected function fail(MeetingImport $import, string $error): void
    {
        $import->update([
            'status' => MeetingImportStatus::Failed,
            'error' => $error,
        ]);
    }

    /**
     * Build the extraction prompt from Fireflies' action items and sentences.
     *
     * @param  array<string, mixed>  $transcript
     */
    protected function buildPrompt(MeetingImport $import, array $transcript): string
    {
        $meetingDate = $import->meeting_date?->toDateString() ?? 'unknown';
        $timezone = config('app.timezone');

        $summary = $transcript['summary'] ?? [];
        $actionItems = $this->stringify($summary['action_items'] ?? null);
        $overview = $this->stringify($summary['overview'] ?? null);

        $rawSentences = $transcript['sentences'] ?? [];
        $sentences = collect(is_array($rawSentences) ? $rawSentences : [])
            ->map(fn ($sentence): string => trim(
                ($sentence['speaker_name'] ?? '').': '.($sentence['text'] ?? '')
            ))
            ->filter()
            ->take(400)
            ->implode("\n");

        return <<<PROMPT
        Meeting date: {$meetingDate} (timezone {$timezone}).

        Fireflies action items:
        {$actionItems}

        Summary:
        {$overview}

        Transcript:
        {$sentences}
        PROMPT;
    }

    /**
     * Flatten a Fireflies field that may be a string or a list into text.
     */
    protected function stringify(mixed $value): string
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item): string => is_string($item) ? $item : (json_encode($item) ?: ''))
                ->implode("\n");
        }

        return is_string($value) ? $value : '(none)';
    }

    /**
     * Parse a Fireflies date (epoch millis or ISO string) to a Carbon instance.
     */
    protected function parseDate(mixed $value): ?Carbon
    {
        if (is_numeric($value)) {
            return Carbon::createFromTimestampMs((int) $value);
        }

        if (is_string($value) && trim($value) !== '') {
            return rescue(fn () => Carbon::parse($value), null, false);
        }

        return null;
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
