<?php

namespace Database\Factories;

use App\Enums\MeetingSource;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source' => MeetingSource::Fireflies,
            'fireflies_meeting_id' => (string) Str::ulid(),
            'title' => fake()->sentence(3),
            'meeting_date' => now(),
            'status' => MeetingStatus::Processing,
            'error' => null,
            'processed_at' => null,
        ];
    }

    /**
     * Indicate that the meeting was created manually (no Fireflies import).
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => MeetingSource::Manual,
            'fireflies_meeting_id' => null,
            'status' => MeetingStatus::Draft,
            'notes' => fake()->paragraph(),
        ]);
    }

    /**
     * Indicate that the meeting's suggestions have been generated.
     */
    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MeetingStatus::Ready,
            'processed_at' => now(),
        ]);
    }

    /**
     * Indicate that processing the meeting failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MeetingStatus::Failed,
            'error' => fake()->sentence(),
        ]);
    }

    /**
     * Attach Fireflies-style content to the meeting.
     */
    public function withContent(): static
    {
        return $this->state(fn (array $attributes) => [
            'summary' => fake()->paragraph(),
            'action_items' => "- ".implode("\n- ", fake()->sentences(3)),
            'transcript' => fake()->paragraphs(3, true),
        ]);
    }
}
