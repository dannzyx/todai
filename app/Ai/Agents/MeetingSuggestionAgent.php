<?php

namespace App\Ai\Agents;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * From a meeting (transcript, Fireflies summary/action items, and manual notes)
 * this agent returns two things in one pass: a list of concrete todos and a
 * single project suggestion for the whole meeting.
 *
 * The prompt (built by the caller) lists the user's active projects with a
 * 1-based index; the agent returns either that index or a proposed new project
 * name, so we never rely on the model reproducing an opaque ULID.
 */
class MeetingSuggestionAgent implements Agent, HasStructuredOutput
{
    public function __construct(public User $user) {}

    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
        You turn a meeting into concrete todos and a single project suggestion.

        Task rules:
        - Only return real, actionable tasks. Skip general discussion points,
          opinions and decisions without an action.
        - Merge duplicate or overlapping tasks into one task.
        - Keep titles short and imperative, in English.
        - Only fill due_date (YYYY-MM-DD) when the meeting states a clear
          deadline relative to the meeting date. Otherwise leave it empty.
        - If there are no tasks, return an empty list.

        Project rules — suggest exactly one project for the whole meeting:
        - You are given a numbered list of the user's existing projects.
        - If one existing project clearly fits, set existing_index to its number
          (1-based) and leave new_project_name empty.
        - If none fit, propose a concise new_project_name and leave
          existing_index empty. Never set both.
        - If the meeting is too vague to group, leave both empty with confidence
          "low".
        - "confidence" is low, medium or high; "reasoning" is one short sentence.
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
            'project' => $schema->object(fn ($schema) => [
                'existing_index' => $schema->integer()->nullable()->required(),
                'new_project_name' => $schema->string()->nullable()->required(),
                'confidence' => $schema->string()->enum(['low', 'medium', 'high'])->required(),
                'reasoning' => $schema->string()->required(),
            ])->required(),
            'tasks' => $schema->array()->items(
                $schema->object(fn ($schema) => [
                    'title' => $schema->string()->required(),
                    'description' => $schema->string()->nullable()->required(),
                    'due_date' => $schema->string()->nullable()->required(), // YYYY-MM-DD
                ])
            )->required(),
        ];
    }
}
