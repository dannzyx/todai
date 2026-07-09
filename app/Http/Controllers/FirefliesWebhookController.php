<?php

namespace App\Http\Controllers;

use App\Enums\MeetingSource;
use App\Enums\MeetingStatus;
use App\Jobs\ProcessFirefliesMeeting;
use App\Models\FirefliesIntegration;
use App\Models\Meeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FirefliesWebhookController extends Controller
{
    /**
     * Handle an incoming Fireflies webhook. The token in the URL identifies the
     * owning user; the payload itself carries no identity.
     */
    public function __invoke(Request $request, string $token): JsonResponse
    {
        // Resolve the user by their capability token. Stay opaque on miss.
        $integration = FirefliesIntegration::query()
            ->where('webhook_token', $token)
            ->first();

        abort_if($integration === null, 404);

        // Verify the HMAC signature over the raw body when a secret is configured.
        if ($integration->webhook_secret !== null) {
            abort_unless(
                $this->signatureIsValid($request, $integration->webhook_secret),
                401,
            );
        }

        // Only act on the transcription-complete event; acknowledge the rest.
        $eventType = (string) $request->input('eventType', '');
        $meetingId = (string) $request->input('meetingId', '');

        if (! str_contains(strtolower($eventType), 'transcription') || $meetingId === '') {
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

        return response()->json(['ok' => true]);
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
