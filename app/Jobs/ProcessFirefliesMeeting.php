<?php

namespace App\Jobs;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Services\FirefliesClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

class ProcessFirefliesMeeting implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Meeting $meeting) {}

    /**
     * Fetch the transcript, persist the meeting's content, then hand off to
     * GenerateMeetingSuggestions so the todos are ready without a manual trigger.
     */
    public function handle(FirefliesClient $client): void
    {
        $meeting = $this->meeting->fresh();

        if ($meeting === null || $meeting->status === MeetingStatus::Ready) {
            return;
        }

        $integration = $meeting->user?->firefliesIntegration;

        if ($integration === null) {
            $this->fail($meeting, 'No Fireflies connection found for this user.');

            return;
        }

        try {
            $transcript = $client->transcript($integration->api_key, $meeting->fireflies_meeting_id);

            if ($transcript === null) {
                $this->fail($meeting, 'Could not fetch the transcript from Fireflies.');

                return;
            }

            $summary = $transcript['summary'] ?? [];

            $meeting->update([
                'title' => $transcript['title'] ?? $meeting->title,
                'meeting_date' => $this->parseDate($transcript['date'] ?? null),
                'summary' => $this->stringify($summary['overview'] ?? null),
                'action_items' => $this->stringify($summary['action_items'] ?? null),
                'transcript' => $this->formatSentences($transcript['sentences'] ?? []),
                'status' => MeetingStatus::Processing,
                'error' => null,
            ]);

            GenerateMeetingSuggestions::dispatch($meeting);
        } catch (Throwable $e) {
            $this->fail($meeting, $e->getMessage());
        }
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
     * Flatten Fireflies sentences into a "speaker: text" transcript.
     */
    protected function formatSentences(mixed $rawSentences): ?string
    {
        $sentences = collect(is_array($rawSentences) ? $rawSentences : [])
            ->map(fn ($sentence): string => trim(
                ($sentence['speaker_name'] ?? '').': '.($sentence['text'] ?? '')
            ))
            ->filter()
            ->implode("\n");

        return $sentences !== '' ? $sentences : null;
    }

    /**
     * Flatten a Fireflies field that may be a string or a list into text.
     */
    protected function stringify(mixed $value): ?string
    {
        if (is_array($value)) {
            $text = collect($value)
                ->map(fn ($item): string => is_string($item) ? $item : (json_encode($item) ?: ''))
                ->implode("\n");

            return $text !== '' ? $text : null;
        }

        return is_string($value) && trim($value) !== '' ? $value : null;
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
}
