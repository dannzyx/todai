<?php

use App\Ai\Agents\ProjectClassifierAgent;
use App\Enums\SuggestionConfidence;
use App\Jobs\ClassifyTaskProject;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('queues a classification job when a task lands in the inbox', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('tasks.store'), ['title' => 'Iets doen']);

    Queue::assertPushed(ClassifyTaskProject::class);
});

it('does not queue classification for a task created inside a project', function () {
    Queue::fake();
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)->post(route('tasks.store'), [
        'title' => 'Iets doen',
        'project_id' => $project->id,
    ]);

    Queue::assertNotPushed(ClassifyTaskProject::class);
});

it('writes a suggestion on a good match', function () {
    ProjectClassifierAgent::fake([
        ['project_index' => 2, 'confidence' => 'high', 'reasoning' => 'Past bij sales.'],
    ]);

    $user = User::factory()->create();
    // Ordered by name: "Marketing" (1), "Sales" (2).
    Project::factory()->for($user)->create(['name' => 'Marketing']);
    $sales = Project::factory()->for($user)->create(['name' => 'Sales']);
    $task = Task::factory()->for($user)->create(['title' => 'Offerte maken', 'project_id' => null]);

    (new ClassifyTaskProject($task))->handle();

    $task->refresh();
    expect($task->suggested_project_id)->toBe($sales->id)
        ->and($task->suggestion_confidence)->toBe(SuggestionConfidence::High)
        ->and($task->suggestion_reasoning)->toBe('Past bij sales.')
        ->and($task->project_id)->toBeNull();
});

it('writes no suggestion when nothing fits', function () {
    ProjectClassifierAgent::fake([
        ['project_index' => null, 'confidence' => 'low', 'reasoning' => 'Geen passend project.'],
    ]);

    $user = User::factory()->create();
    Project::factory()->for($user)->create(['name' => 'Marketing']);
    $task = Task::factory()->for($user)->create(['project_id' => null]);

    (new ClassifyTaskProject($task))->handle();

    $task->refresh();
    expect($task->suggested_project_id)->toBeNull()
        ->and($task->suggestion_confidence)->toBeNull()
        ->and($task->suggestion_reasoning)->toBeNull();
});

it('skips classification when the user has no projects', function () {
    ProjectClassifierAgent::fake();
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create(['project_id' => null]);

    (new ClassifyTaskProject($task))->handle();

    ProjectClassifierAgent::assertNeverPrompted();
    expect($task->fresh()->suggested_project_id)->toBeNull();
});

it('skips classification when the task already has a project', function () {
    ProjectClassifierAgent::fake();
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $task = Task::factory()->forProject($project)->create();

    (new ClassifyTaskProject($task))->handle();

    ProjectClassifierAgent::assertNeverPrompted();
});

it('accepts a suggestion, assigning the task and clearing the suggestion', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $task = Task::factory()->for($user)->withSuggestion($project)->create(['project_id' => null]);

    $this->actingAs($user)
        ->patch(route('tasks.suggestion.accept', $task))
        ->assertRedirect();

    $task->refresh();
    expect($task->project_id)->toBe($project->id)
        ->and($task->suggested_project_id)->toBeNull()
        ->and($task->suggestion_confidence)->toBeNull();
});

it('dismisses a suggestion without assigning', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $task = Task::factory()->for($user)->withSuggestion($project)->create(['project_id' => null]);

    $this->actingAs($user)
        ->patch(route('tasks.suggestion.dismiss', $task))
        ->assertRedirect();

    $task->refresh();
    expect($task->project_id)->toBeNull()
        ->and($task->suggested_project_id)->toBeNull();
});

it('re-runs the suggestion on demand', function () {
    Queue::fake();
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create(['project_id' => null]);

    $this->actingAs($user)
        ->patch(route('tasks.suggest', $task))
        ->assertRedirect();

    Queue::assertPushed(ClassifyTaskProject::class);
});

it('forbids acting on another user suggestion', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $task = Task::factory()->for($owner)->withSuggestion($project)->create(['project_id' => null]);

    $this->actingAs($intruder)->patch(route('tasks.suggestion.accept', $task))->assertForbidden();
    $this->actingAs($intruder)->patch(route('tasks.suggestion.dismiss', $task))->assertForbidden();
    $this->actingAs($intruder)->patch(route('tasks.suggest', $task))->assertForbidden();

    expect($task->fresh()->project_id)->toBeNull();
});
