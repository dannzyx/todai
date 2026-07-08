<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CreateTask;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Todai's conversational assistant. When the user describes work, it creates one
 * task per distinct action via the CreateTask tool, then replies briefly in Dutch.
 */
#[MaxSteps(12)]
class ChatAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    /**
     * Tasks created during the current turn (for the UI side list).
     *
     * @var Collection<int, Task>
     */
    public Collection $createdTasks;

    public function __construct(public User $user)
    {
        $this->createdTasks = collect();
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $today = now()->toDateString();
        $timezone = config('app.timezone');
        $projects = $this->user->projects()->active()->orderBy('name')->pluck('name');
        $projectList = $projects->isNotEmpty()
            ? $projects->map(fn (string $name) => "- {$name}")->implode("\n")
            : '- (nog geen projecten)';

        return <<<INSTRUCTIONS
        Je bent Todai, een rustige persoonlijke assistent. Antwoord altijd in het
        Nederlands.

        Wanneer de gebruiker werk beschrijft, maak je voor elke afzonderlijke actie
        precies één taak aan met de tool CreateTask. Splits samengestelde verzoeken
        op in losse taken.

        Datums:
        - Vandaag is {$today} (tijdzone {$timezone}).
        - Reken relatieve datums ("morgen", "volgende week", "deze week") hiernaar
          om en geef due_date als YYYY-MM-DD. Geen duidelijke datum? Laat due_date leeg.

        Projecten:
        - Verzin nooit een project. Vul het project-veld alleen als de gebruiker
          duidelijk een van deze bestaande projecten noemt:
        {$projectList}
        - Taken zonder duidelijk project laat je in de inbox vallen; die krijgen
          later automatisch een projectvoorstel.

        Nadat je de taken hebt aangemaakt, geef je een korte bevestiging in het
        Nederlands van wat je hebt gemaakt. Maak geen taken aan als de gebruiker
        alleen een vraag stelt.
        INSTRUCTIONS;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return iterable<int, Tool>
     */
    public function tools(): iterable
    {
        return [
            new CreateTask($this->user, $this->createdTasks),
        ];
    }
}
