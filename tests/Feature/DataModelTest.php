<?php

use App\Enums\MeetingStatus;
use App\Enums\SuggestionConfidence;
use App\Enums\TaskSource;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;

it('uses ulids for primary keys', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $task = Task::factory()->for($user)->create();

    expect($user->id)->toBeString()
        ->and(strlen($user->id))->toBe(26)
        ->and(strlen($project->id))->toBe(26)
        ->and(strlen($task->id))->toBe(26);
});

it('casts task enums and dates', function () {
    $task = Task::factory()->create([
        'source' => TaskSource::Chat,
        'due_date' => '2026-07-08',
        'completed_at' => now(),
        'suggestion_confidence' => SuggestionConfidence::High,
    ]);

    expect($task->source)->toBe(TaskSource::Chat)
        ->and($task->suggestion_confidence)->toBe(SuggestionConfidence::High)
        ->and($task->due_date)->toBeInstanceOf(CarbonInterface::class)
        ->and($task->isCompleted())->toBeTrue();
});

it('relates projects and tasks to a user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $task = Task::factory()->forProject($project)->create();

    expect($task->user->id)->toBe($user->id)
        ->and($task->project->id)->toBe($project->id)
        ->and($user->projects)->toHaveCount(1)
        ->and($user->tasks)->toHaveCount(1)
        ->and($project->tasks)->toHaveCount(1)
        ->and($task->isInInbox())->toBeFalse();
});

it('treats a task without a project as inbox', function () {
    $task = Task::factory()->create(['project_id' => null]);

    expect($task->isInInbox())->toBeTrue();
});

it('scopes active projects away from archived ones', function () {
    $user = User::factory()->create();
    Project::factory()->for($user)->create();
    Project::factory()->for($user)->archived()->create();

    expect(Project::active()->count())->toBe(1)
        ->and(Project::count())->toBe(2);
});

it('relates meetings to their tasks', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->for($user)->ready()->create();
    $task = Task::factory()->for($user)->create([
        'source' => TaskSource::Fireflies,
        'meeting_id' => $meeting->id,
    ]);

    expect($meeting->status)->toBe(MeetingStatus::Ready)
        ->and($meeting->tasks)->toHaveCount(1)
        ->and($task->meeting->id)->toBe($meeting->id);
});

it('exposes a pending ai suggestion', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $task = Task::factory()->for($user)->withSuggestion($project)->create();

    expect($task->hasSuggestion())->toBeTrue()
        ->and($task->suggestedProject->id)->toBe($project->id)
        ->and($task->suggestion_confidence)->toBe(SuggestionConfidence::High);
});
