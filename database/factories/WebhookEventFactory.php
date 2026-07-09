<?php

namespace Database\Factories;

use App\Enums\WebhookOutcome;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $meetingId = (string) Str::ulid();

        return [
            'source' => 'fireflies',
            'user_id' => null,
            'outcome' => WebhookOutcome::Accepted,
            'event_type' => 'Transcription completed',
            'fireflies_meeting_id' => $meetingId,
            'signed' => false,
            'ip' => fake()->ipv4(),
            'payload' => [
                'eventType' => 'Transcription completed',
                'meetingId' => $meetingId,
            ],
        ];
    }
}
