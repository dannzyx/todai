<?php

namespace App\Ai\Agents;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Classifies a single task into one of the user's existing projects, or none.
 *
 * The prompt (built by the caller) lists the user's active projects with a
 * 1-based index; the agent returns the chosen index (or null) so we never
 * rely on the model reproducing an opaque ULID.
 */
#[UseCheapestModel]
class ProjectClassifierAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(public User $user) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
        You are Todai's classification assistant. You are given a single task and a
        numbered list of the user's existing projects. Pick the project that best
        fits the task.

        Rules:
        - Only choose from the projects provided. Never invent a project.
        - If no project is a genuinely good fit, return null for project_index and
          confidence "low".
        - "project_index" is the number from the list (1-based), or null.
        - "confidence" is low, medium or high.
        - "reasoning" is one short sentence in English explaining your choice.
        INSTRUCTIONS;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        // OpenAI strict structured outputs require every property in `required`;
        // optional fields are expressed as required + nullable.
        return [
            'project_index' => $schema->integer()->nullable()->required(),
            'confidence' => $schema->string()->enum(['low', 'medium', 'high'])->required(),
            'reasoning' => $schema->string()->required(),
        ];
    }
}
