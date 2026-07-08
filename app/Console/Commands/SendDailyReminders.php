<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\DailyTaskReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('todai:send-daily-reminders')]
#[Description('Send each user their due-today and overdue tasks for the morning.')]
class SendDailyReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sendEmpty = (bool) config('todai.reminders.send_empty', false);
        $sent = 0;

        User::query()->chunkById(200, function (Collection $users) use ($sendEmpty, &$sent): void {
            foreach ($users as $user) {
                /** @var User $user */
                $timezone = $user->getAttribute('timezone') ?: config('app.timezone');
                $today = now($timezone)->toDateString();

                $dueToday = $user->tasks()
                    ->with('project:id,name')
                    ->incomplete()
                    ->whereDate('due_date', $today)
                    ->orderBy('due_date')
                    ->get();

                $overdue = $user->tasks()
                    ->with('project:id,name')
                    ->incomplete()
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', $today)
                    ->orderBy('due_date')
                    ->get();

                if ($dueToday->isEmpty() && $overdue->isEmpty() && ! $sendEmpty) {
                    continue;
                }

                $user->notify(new DailyTaskReminder($dueToday, $overdue));
                $sent++;
            }
        });

        $this->info("Herinneringen verstuurd: {$sent}.");

        return self::SUCCESS;
    }
}
