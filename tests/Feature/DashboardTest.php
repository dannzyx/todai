<?php

use App\Models\Task;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard buckets tasks into overdue, today and tomorrow', function () {
    $user = User::factory()->create();

    $overdue = Task::factory()->for($user)->overdue()->create();
    $today = Task::factory()->for($user)->dueOn(now()->toDateString())->create();
    $tomorrow = Task::factory()->for($user)->dueOn(now()->addDay()->toDateString())->create();
    Task::factory()->for($user)->dueOn(now()->addDays(2)->toDateString())->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Vandaag')
            ->has('overdue', 1)
            ->has('today', 1)
            ->has('tomorrow', 1)
            ->where('overdue.0.id', $overdue->id)
            ->where('today.0.id', $today->id)
            ->where('tomorrow.0.id', $tomorrow->id)
        );
});

test('completed tasks are excluded from the tomorrow bucket', function () {
    $user = User::factory()->create();

    Task::factory()->for($user)
        ->dueOn(now()->addDay()->toDateString())
        ->completed()
        ->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('tomorrow', 0));
});
