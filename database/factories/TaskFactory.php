<?php

namespace Database\Factories;

use App\Enums\SuggestionConfidence;
use App\Enums\TaskSource;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
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
            'project_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'due_date' => null,
            'completed_at' => null,
            'source' => TaskSource::Manual,
            'meeting_id' => null,
            'suggested_project_id' => null,
            'suggestion_confidence' => null,
            'suggestion_reasoning' => null,
        ];
    }

    /**
     * Assign the task to a project.
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
            'user_id' => $project->user_id,
        ]);
    }

    /**
     * Indicate that the task is due on the given date (defaults to today).
     */
    public function dueOn(?string $date = null): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => $date ?? now()->toDateString(),
        ]);
    }

    /**
     * Indicate that the task is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => now()->subDay()->toDateString(),
        ]);
    }

    /**
     * Indicate that the task is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the task carries a pending AI project suggestion.
     */
    public function withSuggestion(Project $project, SuggestionConfidence $confidence = SuggestionConfidence::High): static
    {
        return $this->state(fn (array $attributes) => [
            'suggested_project_id' => $project->id,
            'suggestion_confidence' => $confidence,
            'suggestion_reasoning' => fake()->sentence(),
        ]);
    }
}
