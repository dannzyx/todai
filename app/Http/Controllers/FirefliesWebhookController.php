<?php

namespace App\Http\Controllers;

use App\Enums\MeetingSource;
use App\Enums\MeetingStatus;
use App\Enums\WebhookOutcome;
use App\Jobs\ProcessFirefliesMeeting;
use App\Models\FirefliesIntegration;
use App\Models\Meeting;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FirefliesWebhookController extends Controller
{
    /**
     * Handle an incoming Fireflies webhook. The token in the URL identifies the
     * owning user; the payload itself carries no identity.
     *
     * Every delivery is logged (payload + outcome) so we can see exactly what
     * Fireflies is sending. The token is truncated to avoid writing the full
     * capability secret to the logs.
     */
    public function __invoke(Request $request, string $token): JsonResponse
    {
        $eventType = $this->eventType($request);
        $meetingId = $this->meetingId($request);

        $context = [
            'token' => Str::limit($token, 8, '…'),
            'ip' => $request->ip(),
            'event_type' => $eventType,
            'meeting_id' => $meetingId,
            'signed' => $request->hasHeader('x-hub-signature'),
            'payload' => $request->all(),
        ];

        Log::info('Fireflies webhook received', $context);

        // Resolve the user by their capability token. Stay opaque on miss.
        $integration = FirefliesIntegration::query()
            ->where('webhook_token', $token)
            ->first();

        if ($integration === null) {
            Log::warning('Fireflies webhook rejected: unknown token', $context);
            $this->record($request, WebhookOutcome::UnknownToken, null);
            abort(404);
        }

        $context['user_id'] = $integration->user_id;

        // Verify the HMAC signature over the raw body when a secret is configured.
        if ($integration->webhook_secret !== null
            && ! $this->signatureIsValid($request, $integration->webhook_secret)) {
            Log::warning('Fireflies webhook rejected: invalid signature', $context);
            $this->record($request, WebhookOutcome::InvalidSignature, $integration->user_id);
            abort(401);
        }

        // Only act on the transcription-complete event; acknowledge the rest.
        if (! str_contains(strtolower($eventType), 'transcription') || $meetingId === '') {
            $this->record($request, WebhookOutcome::Ignored, $integration->user_id);

            return response()->json(['ignored' => true]);
        }

        // Idempotent per user + meeting. Only the first delivery does the work.
        $meeting = Meeting::firstOrCreate(
            ['fireflies_meeting_id' => $meetingId],
            [
                'user_id' => $integration->user_id,
                'source' => MeetingSource::Fireflies,
                'status' => MeetingStatus::Processing,
            ],
        );

        if ($meeting->wasRecentlyCreated) {
            ProcessFirefliesMeeting::dispatch($meeting);
        }

        $this->record(
            $request,
            $meeting->wasRecentlyCreated ? WebhookOutcome::Accepted : WebhookOutcome::Duplicate,
            $integration->user_id,
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Persist an incoming webhook and its outcome so it's visible in the app.
     */
    protected function record(Request $request, WebhookOutcome $outcome, ?string $userId): void
    {
        WebhookEvent::create([
            'source' => 'fireflies',
            'user_id' => $userId,
            'outcome' => $outcome,
            'event_type' => $this->eventType($request) ?: null,
            'fireflies_meeting_id' => $this->meetingId($request) ?: null,
            'signed' => $request->hasHeader('x-hub-signature'),
            'ip' => $request->ip(),
            'payload' => $request->all(),
        ]);
    }

    /**
     * The event type, tolerating both Fireflies namings (eventType / event).
     */
    protected function eventType(Request $request): string
    {
        return (string) $request->input('eventType', $request->input('event', ''));
    }

    /**
     * The meeting id, tolerating both Fireflies namings (meetingId / meeting_id).
     */
    protected function meetingId(Request $request): string
    {
        return (string) $request->input('meetingId', $request->input('meeting_id', ''));
    }

    /**
     * Verify the HMAC-SHA256 signature of the raw request body.
     */
    protected function signatureIsValid(Request $request, string $secret): bool
    {
        $provided = $request->header('x-hub-signature', '');
        $provided = str_starts_with($provided, 'sha256=')
            ? substr($provided, 7)
            : $provided;

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return $provided !== '' && hash_equals($expected, $provided);
    }
}
