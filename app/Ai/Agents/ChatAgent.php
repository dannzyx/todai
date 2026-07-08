<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CreateProject;
use App\Ai\Tools\CreateTask;
use App\Ai\Tools\ListTasks;
use App\Models\Project;
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
 * task per distinct action via the CreateTask tool, then replies briefly in English.
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

    /**
     * Projects created during the current turn.
     *
     * @var Collection<int, Project>
     */
    public Collection $createdProjects;

    public function __construct(public User $user)
    {
        $this->createdTasks = collect();
        $this->createdProjects = collect();
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
            : '- (no projects yet)';

        return <<<INSTRUCTIONS
        You are Todai, a calm personal assistant. Always reply in the same
        language the user wrote their message in (e.g. Dutch question, Dutch
        answer).

        When the user describes work, create exactly one task per distinct action
        using the CreateTask tool. Split compound requests into separate tasks.

        Dates:
        - Today is {$today} (timezone {$timezone}).
        - Resolve relative dates ("tomorrow", "next week", "this week") against that
          and pass due_date as YYYY-MM-DD. No clear date? Leave due_date empty.

        Projects:
        - These are the user's existing projects:
        {$projectList}
        - Only fill a task's project field when the user clearly names one of the
          existing projects above. Never invent or guess a project for a task.
        - When the user explicitly asks to create or start a new project, use the
          CreateProject tool. Create the project first, then you may put tasks in it.
        - Tasks without a clear project fall into the inbox; they get an AI project
          suggestion automatically later on.

        After creating the tasks, give a short confirmation (in the user's own
        language) of what you made. Do not create tasks if the user only asks a
        question.

        Answering questions about existing tasks:
        - Never guess what is on the user's list. Use the ListTasks tool to fetch
          the real tasks first, then answer from what it returns.
        - "What is open for today?" (and similar) means overdue plus due-today
          tasks — call ListTasks with scope "today".
        - Present the results as a short, readable list.
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
            new CreateProject($this->user, $this->createdProjects),
            new ListTasks($this->user),
        ];
    }
}
