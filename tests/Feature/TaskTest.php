<?php

use App\Enums\TaskSource;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('requires authentication for the inbox', function () {
    $this->get(route('tasks.inbox'))->assertRedirect(route('login'));
});

it('quick-adds a task to the inbox as manual source', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('tasks.store'), ['title' => 'Remy bellen'])
        ->assertRedirect();

    $task = Task::sole();

    expect($task->title)->toBe('Remy bellen')
        ->and($task->project_id)->toBeNull()
        ->and($task->source)->toBe(TaskSource::Manual)
        ->and($task->user_id)->toBe($user->id);
});

it('creates a task directly inside a project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('tasks.store'), [
            'title' => 'Offerte afmaken',
            'project_id' => $project->id,
            'due_date' => '2026-07-10',
        ])
        ->assertRedirect();

    expect(Task::sole()->project_id)->toBe($project->id);
});

it('requires a title', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('tasks.store'), ['title' => ''])
        ->assertSessionHasErrors('title');

    expect(Task::count())->toBe(0);
});

it('cannot assign a task to another user project', function () {
    $user = User::factory()->create();
    $othersProject = Project::factory()->create();

    $this->actingAs($user)
        ->post(route('tasks.store'), [
            'title' => 'Hack',
            'project_id' => $othersProject->id,
        ])
        ->assertSessionHasErrors('project_id');
});

it('updates a task', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create(['title' => 'Oud']);

    $this->actingAs($user)
        ->put(route('tasks.update', $task), [
            'title' => 'Nieuw',
            'description' => 'Details',
        ])
        ->assertRedirect();

    expect($task->fresh()->title)->toBe('Nieuw')
        ->and($task->fresh()->description)->toBe('Details');
});

it('moves a task to a project and back to the inbox', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $task = Task::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('tasks.move', $task), ['project_id' => $project->id])
        ->assertRedirect();
    expect($task->fresh()->project_id)->toBe($project->id);

    $this->actingAs($user)
        ->patch(route('tasks.move', $task), ['project_id' => null])
        ->assertRedirect();
    expect($task->fresh()->project_id)->toBeNull();
});

it('sets and clears a due date', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('tasks.due-date', $task), ['due_date' => '2026-07-15'])
        ->assertRedirect();
    expect($task->fresh()->due_date->toDateString())->toBe('2026-07-15');

    $this->actingAs($user)
        ->patch(route('tasks.due-date', $task), ['due_date' => null])
        ->assertRedirect();
    expect($task->fresh()->due_date)->toBeNull();
});

it('toggles completion on and off', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create();

    $this->actingAs($user)->patch(route('tasks.toggle', $task))->assertRedirect();
    expect($task->fresh()->isCompleted())->toBeTrue();

    $this->actingAs($user)->patch(route('tasks.toggle', $task))->assertRedirect();
    expect($task->fresh()->isCompleted())->toBeFalse();
});

it('deletes a task', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create();

    $this->actingAs($user)->delete(route('tasks.destroy', $task))->assertRedirect();

    expect(Task::count())->toBe(0);
});

it('forbids acting on another user task', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $task = Task::factory()->for($owner)->create(['title' => 'Geheim']);

    $this->actingAs($intruder)->put(route('tasks.update', $task), ['title' => 'Hack'])->assertForbidden();
    $this->actingAs($intruder)->patch(route('tasks.toggle', $task))->assertForbidden();
    $this->actingAs($intruder)->patch(route('tasks.move', $task), ['project_id' => null])->assertForbidden();
    $this->actingAs($intruder)->delete(route('tasks.destroy', $task))->assertForbidden();

    expect($task->fresh()->title)->toBe('Geheim');
});

it('shows overdue and due-today tasks on Vandaag', function () {
    $user = User::factory()->create();
    $overdue = Task::factory()->for($user)->overdue()->create();
    $today = Task::factory()->for($user)->dueOn()->create();
    Task::factory()->for($user)->dueOn(now()->addWeek()->toDateString())->create(); // upcoming, excluded

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Vandaag')
            ->has('overdue', 1)
            ->has('today', 1)
            ->where('overdue.0.id', $overdue->id)
            ->where('today.0.id', $today->id)
        );
});

it('includes undated inbox tasks below the agenda on Today', function () {
    $user = User::factory()->create();
    $inboxTask = Task::factory()->for($user)->create(['project_id' => null, 'due_date' => null]);
    Task::factory()->for($user)->overdue()->create(); // on the agenda, excluded from inbox list

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Vandaag')
            ->has('inbox', 1)
            ->where('inbox.0.id', $inboxTask->id)
        );
});

it('lists only inbox tasks on the inbox page', function () {
    $user = User::factory()->create();
    $inbox = Task::factory()->for($user)->create(['project_id' => null]);
    $project = Project::factory()->for($user)->create();
    Task::factory()->forProject($project)->create();

    $this->actingAs($user)
        ->get(route('tasks.inbox'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inbox')
            ->has('tasks', 1)
            ->where('tasks.0.id', $inbox->id)
        );
});
