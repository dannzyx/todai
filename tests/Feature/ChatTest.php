<?php

use App\Ai\Agents\ChatAgent;
use App\Ai\Tools\CreateProject;
use App\Ai\Tools\CreateTask;
use App\Enums\TaskSource;
use App\Jobs\ClassifyTaskProject;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request as ToolRequest;

it('requires authentication for the chat page', function () {
    $this->get(route('chat.index'))->assertRedirect(route('login'));
});

it('renders the chat page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('chat.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Chat'));
});

it('creates multiple correctly-dated tasks from one message', function () {
    Queue::fake();

    ChatAgent::fake([
        new ToolCall('a', 'CreateTask', ['title' => 'Remy bellen', 'due_date' => '2026-07-09']),
        new ToolCall('b', 'CreateTask', ['title' => 'Offerte TalentSquare afmaken', 'due_date' => '2026-07-12']),
        'Ik heb twee taken aangemaakt.',
    ]);

    $user = User::factory()->create();
    $agent = new ChatAgent($user);

    $agent->forUser($user)->prompt('morgen Remy bellen, en offerte TalentSquare afmaken deze week');

    expect(Task::count())->toBe(2)
        ->and($agent->createdTasks)->toHaveCount(2);

    $remy = Task::where('title', 'Remy bellen')->sole();
    expect($remy->source)->toBe(TaskSource::Chat)
        ->and($remy->project_id)->toBeNull()
        ->and($remy->due_date->toDateString())->toBe('2026-07-09');

    expect(Task::where('title', 'Offerte TalentSquare afmaken')->sole()->due_date->toDateString())
        ->toBe('2026-07-12');
});

it('sends a chat message through the controller', function () {
    Queue::fake();
    ChatAgent::fake(['Waarmee kan ik je helpen?']);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('chat.send'), ['message' => 'Hallo Todai'])
        ->assertRedirect(route('chat.index'));

    ChatAgent::assertPrompted('Hallo Todai');
});

it('requires a message to send', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('chat.send'), ['message' => ''])
        ->assertSessionHasErrors('message');
});

// --- CreateTask tool ---------------------------------------------------------

it('creates an inbox task and queues classification', function () {
    Queue::fake();
    $user = User::factory()->create();
    $created = collect();

    $result = (new CreateTask($user, $created))->handle(
        new ToolRequest(['title' => 'Iets doen', 'due_date' => '2026-07-20']),
    );

    $task = Task::sole();
    expect($task->title)->toBe('Iets doen')
        ->and($task->source)->toBe(TaskSource::Chat)
        ->and($task->project_id)->toBeNull()
        ->and($task->due_date->toDateString())->toBe('2026-07-20')
        ->and($created)->toHaveCount(1)
        ->and($result)->toBeString();

    Queue::assertPushed(ClassifyTaskProject::class);
});

it('assigns a task when the user names an existing project', function () {
    Queue::fake();
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['name' => 'Sales']);

    (new CreateTask($user, collect()))->handle(
        new ToolRequest(['title' => 'Offerte', 'project' => 'sales']), // case-insensitive
    );

    expect(Task::sole()->project_id)->toBe($project->id);
    Queue::assertNotPushed(ClassifyTaskProject::class);
});

it('ignores an unknown or foreign project name and lands in the inbox', function () {
    Queue::fake();
    $user = User::factory()->create();
    Project::factory()->create(['name' => 'Andermans project']); // different user

    (new CreateTask($user, collect()))->handle(
        new ToolRequest(['title' => 'Taak', 'project' => 'Andermans project']),
    );

    expect(Task::sole()->project_id)->toBeNull();
});

it('drops an invalid due date', function () {
    Queue::fake();
    $user = User::factory()->create();

    (new CreateTask($user, collect()))->handle(
        new ToolRequest(['title' => 'Taak', 'due_date' => 'morgen']),
    );

    expect(Task::sole()->due_date)->toBeNull();
});

// --- CreateProject tool ------------------------------------------------------

it('creates a project for the user', function () {
    $user = User::factory()->create();
    $created = collect();

    $result = (new CreateProject($user, $created))->handle(
        new ToolRequest(['name' => 'Website herbouw', 'color' => '#6b7280']),
    );

    $project = Project::sole();
    expect($project->name)->toBe('Website herbouw')
        ->and($project->user_id)->toBe($user->id)
        ->and($project->color)->toBe('#6B7280') // normalised to uppercase hex
        ->and($created)->toHaveCount(1)
        ->and($result)->toBeString();
});

it('does not create a duplicate active project', function () {
    $user = User::factory()->create();
    Project::factory()->for($user)->create(['name' => 'Sales']);
    $created = collect();

    (new CreateProject($user, $created))->handle(
        new ToolRequest(['name' => 'sales']), // case-insensitive match
    );

    expect(Project::where('user_id', $user->id)->count())->toBe(1)
        ->and($created)->toHaveCount(0);
});

it('drops an invalid color', function () {
    $user = User::factory()->create();

    (new CreateProject($user, collect()))->handle(
        new ToolRequest(['name' => 'Iets', 'color' => 'blauw']),
    );

    expect(Project::sole()->color)->toBeNull();
});
