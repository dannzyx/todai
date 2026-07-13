<?php

use App\Ai\Agents\MeetingSuggestionAgent;
use App\Enums\MeetingSource;
use App\Enums\MeetingStatus;
use App\Enums\SuggestionStatus;
use App\Enums\TaskSource;
use App\Jobs\GenerateMeetingSuggestions;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskSuggestion;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

// --- CRUD --------------------------------------------------------------------

it('creates a manual meeting', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('meetings.store'), [
            'title' => 'Kickoff',
            'meeting_date' => '2026-07-09',
            'notes' => 'We discussed the roadmap.',
        ])
        ->assertRedirect();

    $meeting = Meeting::sole();
    expect($meeting->title)->toBe('Kickoff')
        ->and($meeting->source)->toBe(MeetingSource::Manual)
        ->and($meeting->status)->toBe(MeetingStatus::Draft)
        ->and($meeting->notes)->toBe('We discussed the roadmap.');
});

it('stores a transcript larger than the notes limit', function () {
    $user = User::factory()->create();
    $transcript = trim(str_repeat('Speaker: a long line of dialogue. ', 3000));

    expect(strlen($transcript))->toBeGreaterThan(20000);

    $this->actingAs($user)
        ->post(route('meetings.store'), [
            'title' => 'Hour-long sync',
            'transcript' => $transcript,
        ])
        ->assertRedirect();

    expect(Meeting::sole()->transcript)->toBe($transcript);
});

it('validates that a manual meeting has a title', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('meetings.store'), ['title' => ''])
        ->assertSessionHasErrors('title');

    expect(Meeting::count())->toBe(0);
});

it('updates a meeting', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->for($user)->manual()->create();

    $this->actingAs($user)
        ->put(route('meetings.update', $meeting), [
            'title' => 'Renamed',
            'notes' => 'Updated notes.',
        ])
        ->assertRedirect();

    expect($meeting->fresh()->title)->toBe('Renamed')
        ->and($meeting->fresh()->notes)->toBe('Updated notes.');
});

it('deletes a meeting', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('meetings.destroy', $meeting))
        ->assertRedirect(route('meetings.index'));

    expect(Meeting::count())->toBe(0);
});

// --- Generation --------------------------------------------------------------

it('dispatches suggestion generation on demand', function () {
    Queue::fake();
    $user = User::factory()->create();
    $meeting = Meeting::factory()->for($user)->manual()->create();

    $this->actingAs($user)
        ->post(route('meetings.generate', $meeting))
        ->assertRedirect();

    expect($meeting->fresh()->status)->toBe(MeetingStatus::Processing);
    Queue::assertPushed(GenerateMeetingSuggestions::class, 1);
});

it('generates todo suggestions and an existing-project suggestion', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['name' => 'Sales']);
    $meeting = Meeting::factory()->for($user)->manual()->withContent()->create();

    MeetingSuggestionAgent::fake([
        [
            'language' => 'English',
            'project' => [
                'existing_index' => 1,
                'new_project_name' => null,
                'confidence' => 'high',
                'reasoning' => 'All todos relate to sales.',
            ],
            'tasks' => [
                ['title' => 'Call Remy', 'description' => null, 'due_date' => '2026-07-10', 'for_me' => true],
                ['title' => 'Send quote', 'description' => 'To TalentSquare', 'due_date' => null, 'for_me' => false],
            ],
        ],
    ]);

    (new GenerateMeetingSuggestions($meeting))->handle();

    $meeting->refresh();
    expect($meeting->status)->toBe(MeetingStatus::Ready)
        ->and($meeting->suggested_project_id)->toBe($project->id)
        ->and($meeting->suggested_project_name)->toBeNull()
        ->and($meeting->language)->toBe('English')
        ->and($meeting->taskSuggestions()->count())->toBe(2);

    $call = TaskSuggestion::where('title', 'Call Remy')->sole();
    expect($call->status)->toBe(SuggestionStatus::Pending)
        ->and($call->due_date->toDateString())->toBe('2026-07-10')
        ->and($call->for_me)->toBeTrue();

    expect(TaskSuggestion::where('title', 'Send quote')->sole()->for_me)->toBeFalse();
});

it('proposes a new project when none fit', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->for($user)->manual()->withContent()->create();

    MeetingSuggestionAgent::fake([
        [
            'language' => 'English',
            'project' => [
                'existing_index' => null,
                'new_project_name' => 'Website relaunch',
                'confidence' => 'medium',
                'reasoning' => 'Distinct new initiative.',
            ],
            'tasks' => [['title' => 'Draft brief', 'description' => null, 'due_date' => null, 'for_me' => false]],
        ],
    ]);

    (new GenerateMeetingSuggestions($meeting))->handle();

    $meeting->refresh();
    expect($meeting->suggested_project_id)->toBeNull()
        ->and($meeting->suggested_project_name)->toBe('Website relaunch');
});

it('replaces still-pending suggestions when regenerating', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->for($user)->manual()->create();
    TaskSuggestion::factory()->for($meeting)->create(['title' => 'Stale']);

    MeetingSuggestionAgent::fake([
        [
            'language' => 'English',
            'project' => ['existing_index' => null, 'new_project_name' => null, 'confidence' => 'low', 'reasoning' => 'n/a'],
            'tasks' => [['title' => 'Fresh', 'description' => null, 'due_date' => null, 'for_me' => false]],
        ],
    ]);

    (new GenerateMeetingSuggestions($meeting))->handle();

    expect($meeting->taskSuggestions()->pluck('title')->all())->toBe(['Fresh']);
});

it('persists the detected language and leaves task content untranslated', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->for($user)->manual()->withContent()->create();

    MeetingSuggestionAgent::fake([
        [
            'language' => 'Dutch',
            'project' => ['existing_index' => null, 'new_project_name' => null, 'confidence' => 'low', 'reasoning' => 'n/a'],
            'tasks' => [
                ['title' => 'Bel Remy terug', 'description' => 'Over het voorstel', 'due_date' => null, 'for_me' => true],
            ],
        ],
    ]);

    (new GenerateMeetingSuggestions($meeting))->handle();

    $meeting->refresh();
    expect($meeting->language)->toBe('Dutch');

    $suggestion = TaskSuggestion::sole();
    expect($suggestion->title)->toBe('Bel Remy terug')
        ->and($suggestion->description)->toBe('Over het voorstel');
});

it('shows the current user\'s own todos before the rest', function () {
    $this->withoutVite();

    $user = User::factory()->create();
    $meeting = Meeting::factory()->for($user)->manual()->create();

    TaskSuggestion::factory()->for($meeting)->create(['title' => 'Someone else task']);
    TaskSuggestion::factory()->for($meeting)->forMe()->create(['title' => 'My task']);

    $this->actingAs($user)
        ->get(route('meetings.show', $meeting))
        ->assertInertia(fn ($page) => $page
            ->component('meetings/Show')
            ->where('meeting.task_suggestions.0.title', 'My task')
            ->where('meeting.task_suggestions.0.for_me', true)
            ->where('meeting.task_suggestions.1.title', 'Someone else task')
            ->where('meeting.task_suggestions.1.for_me', false)
        );
});

// --- Accepting suggestions ---------------------------------------------------

it('accepts a todo suggestion into a task under the meeting project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $meeting = Meeting::factory()->for($user)->manual()->create(['project_id' => $project->id]);
    $suggestion = TaskSuggestion::factory()->for($meeting)->create(['title' => 'Ship it']);

    $this->actingAs($user)
        ->patch(route('meetings.suggestion.accept', [$meeting, $suggestion]))
        ->assertRedirect();

    $task = Task::sole();
    expect($task->title)->toBe('Ship it')
        ->and($task->project_id)->toBe($project->id)
        ->and($task->meeting_id)->toBe($meeting->id)
        ->and($task->source)->toBe(TaskSource::Manual)
        ->and($suggestion->fresh()->status)->toBe(SuggestionStatus::Accepted)
        ->and($suggestion->fresh()->accepted_task_id)->toBe($task->id);
});

it('accepts all pending suggestions at once', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->for($user)->manual()->create();
    TaskSuggestion::factory()->for($meeting)->count(3)->create();

    $this->actingAs($user)
        ->patch(route('meetings.suggestions.accept-all', $meeting))
        ->assertRedirect();

    expect(Task::count())->toBe(3)
        ->and($meeting->taskSuggestions()->where('status', SuggestionStatus::Pending)->count())->toBe(0);
});

it('dismisses a todo suggestion without creating a task', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->for($user)->manual()->create();
    $suggestion = TaskSuggestion::factory()->for($meeting)->create();

    $this->actingAs($user)
        ->patch(route('meetings.suggestion.dismiss', [$meeting, $suggestion]))
        ->assertRedirect();

    expect(Task::count())->toBe(0)
        ->and($suggestion->fresh()->status)->toBe(SuggestionStatus::Dismissed);
});

// --- Project suggestion ------------------------------------------------------

it('accepts an existing-project suggestion', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $meeting = Meeting::factory()->for($user)->manual()->ready()->create([
        'suggested_project_id' => $project->id,
    ]);

    $this->actingAs($user)
        ->patch(route('meetings.project.accept', $meeting))
        ->assertRedirect();

    $meeting->refresh();
    expect($meeting->project_id)->toBe($project->id)
        ->and($meeting->suggested_project_id)->toBeNull();
});

it('creates the proposed project when accepting a new-project suggestion', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->for($user)->manual()->ready()->create([
        'suggested_project_name' => 'Onboarding',
    ]);

    $this->actingAs($user)
        ->patch(route('meetings.project.accept', $meeting))
        ->assertRedirect();

    $project = Project::where('name', 'Onboarding')->sole();
    expect($meeting->fresh()->project_id)->toBe($project->id)
        ->and($meeting->fresh()->suggested_project_name)->toBeNull();
});

it('dismisses a project suggestion', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->for($user)->manual()->create([
        'suggested_project_name' => 'Nope',
    ]);

    $this->actingAs($user)
        ->patch(route('meetings.project.dismiss', $meeting))
        ->assertRedirect();

    expect($meeting->fresh()->suggested_project_name)->toBeNull()
        ->and($meeting->fresh()->project_id)->toBeNull();
});

// --- Authorization -----------------------------------------------------------

it('forbids viewing another users meeting', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create();

    $this->actingAs($user)
        ->get(route('meetings.show', $meeting))
        ->assertForbidden();
});

it('forbids acting on another users meeting', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create();
    $suggestion = TaskSuggestion::factory()->for($meeting)->create();

    $this->actingAs($user)
        ->patch(route('meetings.suggestion.accept', [$meeting, $suggestion]))
        ->assertForbidden();

    expect(Task::count())->toBe(0);
});
