<?php

use App\Models\Task;
use App\Models\User;
use App\Notifications\DailyTaskReminder;
use Illuminate\Support\Facades\Notification;

it('sends a reminder with the right due-today and overdue tasks', function () {
    Notification::fake();
    $this->freezeTime();

    $user = User::factory()->create();
    $today = Task::factory()->for($user)->dueOn(now()->toDateString())->create();
    $overdue = Task::factory()->for($user)->overdue()->create();
    Task::factory()->for($user)->dueOn(now()->addWeek()->toDateString())->create(); // upcoming, excluded
    Task::factory()->for($user)->dueOn(now()->toDateString())->completed()->create(); // done, excluded

    $this->artisan('todai:send-daily-reminders')->assertSuccessful();

    Notification::assertSentTo(
        $user,
        DailyTaskReminder::class,
        function (DailyTaskReminder $notification, array $channels) use ($today, $overdue) {
            return $notification->dueToday->count() === 1
                && $notification->dueToday->first()->id === $today->id
                && $notification->overdue->count() === 1
                && $notification->overdue->first()->id === $overdue->id
                && in_array('mail', $channels, true)
                && in_array('database', $channels, true);
        },
    );
});

it('skips users with nothing due by default', function () {
    Notification::fake();

    $withTasks = User::factory()->create();
    Task::factory()->for($withTasks)->overdue()->create();
    $empty = User::factory()->create();

    $this->artisan('todai:send-daily-reminders')->assertSuccessful();

    Notification::assertSentTo($withTasks, DailyTaskReminder::class);
    Notification::assertNotSentTo($empty, DailyTaskReminder::class);
});

it('sends empty reminders when the flag is enabled', function () {
    config(['todai.reminders.send_empty' => true]);
    Notification::fake();

    $user = User::factory()->create();

    $this->artisan('todai:send-daily-reminders')->assertSuccessful();

    Notification::assertSentTo($user, DailyTaskReminder::class);
});

it('does not count another user tasks', function () {
    Notification::fake();

    $user = User::factory()->create();
    Task::factory()->for($user)->overdue()->create();
    $other = User::factory()->create();
    Task::factory()->for($other)->overdue()->create();

    $this->artisan('todai:send-daily-reminders')->assertSuccessful();

    Notification::assertSentTo($user, DailyTaskReminder::class, function (DailyTaskReminder $n) {
        return $n->overdue->count() === 1;
    });
});
