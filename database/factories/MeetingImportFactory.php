<?php

namespace Database\Factories;

use App\Enums\MeetingImportStatus;
use App\Models\MeetingImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MeetingImport>
 */
class MeetingImportFactory extends Factory
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
            'fireflies_meeting_id' => (string) Str::ulid(),
            'title' => fake()->sentence(3),
            'meeting_date' => now(),
            'status' => MeetingImportStatus::Pending,
            'error' => null,
            'processed_at' => null,
        ];
    }

    /**
     * Indicate that the meeting import has been processed.
     */
    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MeetingImportStatus::Processed,
            'processed_at' => now(),
        ]);
    }

    /**
     * Indicate that the meeting import failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MeetingImportStatus::Failed,
            'error' => fake()->sentence(),
        ]);
    }
}
