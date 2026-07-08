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
        Je bent Todai's classificatie-assistent. Je krijgt één taak en een
        genummerde lijst met bestaande projecten van de gebruiker. Kies het
        project dat het beste bij de taak past.

        Regels:
        - Kies alleen uit de aangeboden projecten. Verzin nooit een project.
        - Past er geen enkel project echt goed? Geef dan null terug voor
          project_index en confidence "low".
        - "project_index" is het nummer uit de lijst (1-gebaseerd), of null.
        - "confidence" is low, medium of high.
        - "reasoning" is één korte zin in het Nederlands die je keuze uitlegt.
        INSTRUCTIONS;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_index' => $schema->integer()->nullable(),
            'confidence' => $schema->string()->enum(['low', 'medium', 'high'])->required(),
            'reasoning' => $schema->string()->required(),
        ];
    }
}
