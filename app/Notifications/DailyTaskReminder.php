<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class DailyTaskReminder extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, Task>  $dueToday
     * @param  Collection<int, Task>  $overdue
     */
    public function __construct(
        public Collection $dueToday,
        public Collection $overdue,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * Structured so a Slack or push channel can be added without touching callers.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable instanceof User ? $notifiable->name : null;

        $mail = (new MailMessage)
            ->subject('Je taken voor vandaag')
            ->greeting('Goedemorgen'.($name ? ", {$name}" : '').'!');

        if ($this->overdue->isNotEmpty()) {
            $mail->line("**Te laat ({$this->overdue->count()})**");
            foreach ($this->groupByProject($this->overdue) as $project => $titles) {
                $mail->line("_{$project}_");
                foreach ($titles as $title) {
                    $mail->line("• {$title}");
                }
            }
        }

        if ($this->dueToday->isNotEmpty()) {
            $mail->line("**Vandaag ({$this->dueToday->count()})**");
            foreach ($this->groupByProject($this->dueToday) as $project => $titles) {
                $mail->line("_{$project}_");
                foreach ($titles as $title) {
                    $mail->line("• {$title}");
                }
            }
        }

        if ($this->overdue->isEmpty() && $this->dueToday->isEmpty()) {
            $mail->line('Niets gepland vandaag.');
        }

        return $mail
            ->action('Open Todai', url('/'))
            ->line('Fijne dag!');
    }

    /**
     * Get the array representation stored for the in-app (database) channel.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'due_today_count' => $this->dueToday->count(),
            'overdue_count' => $this->overdue->count(),
            'tasks' => $this->dueToday->concat($this->overdue)
                ->map(fn (Task $task): array => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'due_date' => $task->due_date?->toDateString(),
                    'project' => data_get($task, 'project.name'),
                ])
                ->all(),
        ];
    }

    /**
     * Group task titles by their project name (Inbox for unassigned tasks).
     *
     * @param  Collection<int, Task>  $tasks
     * @return Collection<string, Collection<int, string>>
     */
    protected function groupByProject(Collection $tasks): Collection
    {
        return $tasks
            ->groupBy(fn (Task $task): string => data_get($task, 'project.name') ?? 'Inbox')
            ->map(fn (Collection $group): Collection => $group->map(fn (Task $task): string => $task->title));
    }
}
