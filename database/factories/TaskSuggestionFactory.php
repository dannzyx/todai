<?php

namespace Database\Factories;

use App\Enums\SuggestionStatus;
use App\Models\Meeting;
use App\Models\TaskSuggestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskSuggestion>
 */
class TaskSuggestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'title' => fake()->sentence(3),
            'description' => null,
            'due_date' => null,
            'for_me' => false,
            'status' => SuggestionStatus::Pending,
            'accepted_task_id' => null,
        ];
    }

    /**
     * Indicate that the suggestion belongs to the current user.
     */
    public function forMe(): static
    {
        return $this->state(fn (array $attributes) => [
            'for_me' => true,
        ]);
    }

    /**
     * Indicate that the suggestion was accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SuggestionStatus::Accepted,
        ]);
    }

    /**
     * Indicate that the suggestion was dismissed.
     */
    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SuggestionStatus::Dismissed,
        ]);
    }
}
