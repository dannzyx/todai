<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Morning task reminders. Hour is configurable via TODAI_REMINDERS_HOUR.
$reminderHour = (int) config('todai.reminders.hour', 7);
Schedule::command('todai:send-daily-reminders')
    ->dailyAt(sprintf('%02d:00', $reminderHour));
