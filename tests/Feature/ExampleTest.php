<?php

use App\Models\User;

test('guests visiting the home page are redirected to login', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('authenticated users visiting the home page are redirected to the dashboard', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('home'))->assertRedirect(route('dashboard'));
});
