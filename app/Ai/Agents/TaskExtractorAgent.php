<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Extracts concrete, actionable tasks from a meeting transcript and Fireflies'
 * own action-item list. Titles are short and imperative, in Dutch; a due date is
 * only inferred when the transcript clearly states one.
 */
class TaskExtractorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
        Je haalt concrete, uitvoerbare taken uit een vergadertranscript en de door
        Fireflies gegenereerde actiepunten.

        Regels:
        - Geef alleen echte, uitvoerbare taken terug. Sla algemene bespreekpunten,
          meningen en beslissingen zonder actie over.
        - Voeg dubbele of overlappende taken samen tot één taak.
        - Houd titels kort en in de gebiedende wijs, in het Nederlands.
        - Vul due_date (YYYY-MM-DD) alleen in als het transcript een duidelijke
          deadline noemt ten opzichte van de vergaderdatum. Anders laat je die leeg.
        - Zijn er geen taken? Geef dan een lege lijst terug.
        INSTRUCTIONS;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'tasks' => $schema->array()->items(
                $schema->object(fn ($schema) => [
                    'title' => $schema->string()->required(),
                    'description' => $schema->string()->nullable(),
                    'due_date' => $schema->string()->nullable(), // YYYY-MM-DD
                ])
            )->required(),
        ];
    }
}
