<?php

use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('requires authentication for the projects index', function () {
    $this->get(route('projects.index'))->assertRedirect(route('login'));
});

it('lists only the current user projects, split by archive state', function () {
    $user = User::factory()->create();
    $mine = Project::factory()->for($user)->create(['name' => 'Mijn project']);
    $archived = Project::factory()->for($user)->archived()->create(['name' => 'Oud project']);
    Project::factory()->create(['name' => 'Andermans project']);

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/Index')
            ->has('active', 1)
            ->has('archived', 1)
            ->where('active.0.id', $mine->id)
            ->where('archived.0.id', $archived->id)
        );
});

it('creates a project scoped to the user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.store'), [
            'name' => 'Nieuw project',
            'description' => 'Een omschrijving',
            'color' => '#22A9B8',
        ])
        ->assertRedirect(route('projects.index'));

    $this->assertDatabaseHas('projects', [
        'user_id' => $user->id,
        'name' => 'Nieuw project',
    ]);
});

it('validates that a project needs a name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.store'), ['name' => ''])
        ->assertSessionHasErrors('name');

    expect(Project::count())->toBe(0);
});

it('renames a project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['name' => 'Oud']);

    $this->actingAs($user)
        ->put(route('projects.update', $project), ['name' => 'Nieuw'])
        ->assertRedirect();

    expect($project->fresh()->name)->toBe('Nieuw');
});

it('archives and unarchives a project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('projects.archive', $project))
        ->assertRedirect();

    expect($project->fresh()->isArchived())->toBeTrue();

    $this->actingAs($user)
        ->patch(route('projects.unarchive', $project))
        ->assertRedirect();

    expect($project->fresh()->isArchived())->toBeFalse();
});

it('forbids acting on another user project', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->for($owner)->create();

    $this->actingAs($intruder)->get(route('projects.show', $project))->assertForbidden();
    $this->actingAs($intruder)->put(route('projects.update', $project), ['name' => 'Hack'])->assertForbidden();
    $this->actingAs($intruder)->patch(route('projects.archive', $project))->assertForbidden();

    expect($project->fresh()->name)->not->toBe('Hack');
});
