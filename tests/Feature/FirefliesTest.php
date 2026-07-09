<?php

use App\Enums\MeetingStatus;
use App\Jobs\GenerateMeetingSuggestions;
use App\Jobs\ProcessFirefliesMeeting;
use App\Models\FirefliesIntegration;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use App\Services\FirefliesClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function firefliesPayload(string $meetingId = 'MEETING-1', string $event = 'Transcription completed'): array
{
    return ['meetingId' => $meetingId, 'eventType' => $event];
}

// --- Webhook endpoint --------------------------------------------------------

it('returns 404 for an unknown webhook token', function () {
    $this->postJson('/webhooks/fireflies/does-not-exist', firefliesPayload())
        ->assertNotFound();

    expect(Meeting::count())->toBe(0);
});

it('rejects a webhook with an invalid signature', function () {
    $integration = FirefliesIntegration::factory()->withSecret('topsecret')->create();

    $this->postJson("/webhooks/fireflies/{$integration->webhook_token}", firefliesPayload(), [
        'x-hub-signature' => 'sha256=deadbeef',
    ])->assertStatus(401);

    expect(Meeting::count())->toBe(0);
});

it('accepts a webhook with a valid signature', function () {
    Queue::fake();
    $integration = FirefliesIntegration::factory()->withSecret('topsecret')->create();

    $content = json_encode(firefliesPayload());
    $signature = 'sha256='.hash_hmac('sha256', $content, 'topsecret');

    $this->call('POST', "/webhooks/fireflies/{$integration->webhook_token}", [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_HUB_SIGNATURE' => $signature,
    ], $content)->assertOk();

    expect(Meeting::count())->toBe(1);
    Queue::assertPushed(ProcessFirefliesMeeting::class, 1);
});

it('ignores non-transcription events', function () {
    Queue::fake();
    $integration = FirefliesIntegration::factory()->create();

    $this->postJson("/webhooks/fireflies/{$integration->webhook_token}", firefliesPayload('M', 'Some other event'))
        ->assertOk();

    expect(Meeting::count())->toBe(0);
    Queue::assertNotPushed(ProcessFirefliesMeeting::class);
});

it('is idempotent across webhook redelivery', function () {
    Queue::fake();
    $integration = FirefliesIntegration::factory()->create();
    $url = "/webhooks/fireflies/{$integration->webhook_token}";

    $this->postJson($url, firefliesPayload('SAME'))->assertOk();
    $this->postJson($url, firefliesPayload('SAME'))->assertOk();

    expect(Meeting::where('fireflies_meeting_id', 'SAME')->count())->toBe(1);
    Queue::assertPushed(ProcessFirefliesMeeting::class, 1);
});

it('routes a meeting to the token owner, never another user', function () {
    Queue::fake();
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $integrationA = FirefliesIntegration::factory()->for($userA)->create();
    FirefliesIntegration::factory()->for($userB)->create();

    $this->postJson("/webhooks/fireflies/{$integrationA->webhook_token}", firefliesPayload('X'))
        ->assertOk();

    $meeting = Meeting::sole();
    expect($meeting->user_id)->toBe($userA->id)
        ->and($meeting->user_id)->not->toBe($userB->id);
});

// --- Processing job ----------------------------------------------------------

it('persists the transcript and hands off to suggestion generation', function () {
    Queue::fake();

    Http::fake([
        'api.fireflies.ai/*' => Http::response(['data' => ['transcript' => [
            'title' => 'Weekstart',
            'date' => 1_752_000_000_000,
            'summary' => ['action_items' => "Remy bellen\nOfferte sturen", 'overview' => 'Kort overleg.'],
            'sentences' => [['text' => 'We moeten Remy bellen.', 'speaker_name' => 'Danny']],
        ]]]),
    ]);

    $user = User::factory()->create();
    FirefliesIntegration::factory()->for($user)->create();
    $meeting = Meeting::factory()->for($user)->create(['fireflies_meeting_id' => 'M-EXTRACT']);

    (new ProcessFirefliesMeeting($meeting))->handle(new FirefliesClient);

    $meeting->refresh();
    expect($meeting->status)->toBe(MeetingStatus::Processing)
        ->and($meeting->title)->toBe('Weekstart')
        ->and($meeting->summary)->toBe('Kort overleg.')
        ->and($meeting->action_items)->toBe("Remy bellen\nOfferte sturen")
        ->and($meeting->transcript)->toBe('Danny: We moeten Remy bellen.')
        ->and(Task::count())->toBe(0);

    Queue::assertPushed(GenerateMeetingSuggestions::class, 1);
});

it('marks the meeting failed when the transcript cannot be fetched', function () {
    Queue::fake();
    Http::fake(['api.fireflies.ai/*' => Http::response([], 500)]);

    $user = User::factory()->create();
    FirefliesIntegration::factory()->for($user)->create();
    $meeting = Meeting::factory()->for($user)->create();

    (new ProcessFirefliesMeeting($meeting))->handle(new FirefliesClient);

    $meeting->refresh();
    expect($meeting->status)->toBe(MeetingStatus::Failed)
        ->and($meeting->error)->not->toBeNull();

    Queue::assertNotPushed(GenerateMeetingSuggestions::class);
});

it('does nothing for an already-ready meeting', function () {
    Http::fake();
    $user = User::factory()->create();
    FirefliesIntegration::factory()->for($user)->create();
    $meeting = Meeting::factory()->for($user)->ready()->create();

    (new ProcessFirefliesMeeting($meeting))->handle(new FirefliesClient);

    Http::assertNothingSent();
});

// --- Settings connection -----------------------------------------------------

it('connects a fireflies account with a valid key', function () {
    Http::fake([
        'api.fireflies.ai/*' => Http::response(['data' => ['user' => ['email' => 'me@fireflies.ai']]]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('fireflies.update'), ['api_key' => 'valid-key'])
        ->assertRedirect(route('fireflies.edit'));

    $integration = $user->fresh()->firefliesIntegration;
    expect($integration)->not->toBeNull()
        ->and($integration->fireflies_email)->toBe('me@fireflies.ai')
        ->and($integration->api_key)->toBe('valid-key')
        ->and($integration->webhook_token)->not->toBeEmpty();

    // Stored encrypted, not in plain text.
    $raw = DB::table('fireflies_integrations')->first();
    expect($raw->api_key)->not->toBe('valid-key');
});

it('rejects an invalid fireflies key', function () {
    Http::fake(['api.fireflies.ai/*' => Http::response([], 401)]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('fireflies.update'), ['api_key' => 'bad-key'])
        ->assertSessionHasErrors('api_key');

    expect($user->fresh()->firefliesIntegration)->toBeNull();
});

it('rotates the webhook token', function () {
    $user = User::factory()->create();
    $integration = FirefliesIntegration::factory()->for($user)->create();
    $old = $integration->webhook_token;

    $this->actingAs($user)->patch(route('fireflies.rotate'))->assertRedirect();

    expect($integration->fresh()->webhook_token)->not->toBe($old);
});

it('disconnects fireflies', function () {
    $user = User::factory()->create();
    FirefliesIntegration::factory()->for($user)->create();

    $this->actingAs($user)->delete(route('fireflies.destroy'))->assertRedirect();

    expect($user->fresh()->firefliesIntegration)->toBeNull();
});
