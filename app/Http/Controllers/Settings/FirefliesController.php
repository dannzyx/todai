<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\FirefliesIntegration;
use App\Models\WebhookEvent;
use App\Services\FirefliesClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FirefliesController extends Controller
{
    /**
     * Show the Fireflies connection settings.
     */
    public function edit(Request $request): Response
    {
        $integration = $request->user()->firefliesIntegration;

        return Inertia::render('settings/Fireflies', [
            'connected' => $integration !== null,
            'firefliesEmail' => $integration?->fireflies_email,
            'hasSecret' => $integration?->webhook_secret !== null,
            'webhookUrl' => $integration
                ? url("/webhooks/fireflies/{$integration->webhook_token}")
                : null,
            'isAdmin' => $request->user()->isAdmin(),
        ]);
    }

    /**
     * Show the history of incoming Fireflies webhooks and their outcome.
     *
     * App-wide operational view (includes rejected deliveries that can't be
     * attributed to a user), so it's gated to admins — see config/todai.php.
     */
    public function webhooks(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        $events = WebhookEvent::query()
            ->where('source', 'fireflies')
            ->with('user:id,name,email')
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (WebhookEvent $event): array => [
                'id' => $event->id,
                'outcome' => $event->outcome->value,
                'outcome_label' => $event->outcome->label(),
                'event_type' => $event->event_type,
                'fireflies_meeting_id' => $event->fireflies_meeting_id,
                'signed' => $event->signed,
                'ip' => $event->ip,
                'payload' => $event->payload,
                'user' => $event->user
                    ? ['name' => $event->user->name, 'email' => $event->user->email]
                    : null,
                'created_at' => $event->created_at?->toIso8601String(),
            ]);

        return Inertia::render('settings/fireflies/Webhooks', [
            'events' => $events,
        ]);
    }

    /**
     * Connect (or update) the user's Fireflies account.
     */
    public function update(Request $request, FirefliesClient $client): RedirectResponse
    {
        $validated = $request->validate([
            'api_key' => ['required', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $email = $client->verifyEmail($validated['api_key']);

        if ($email === null) {
            return back()->withErrors([
                'api_key' => 'This API key is invalid or Fireflies is unreachable.',
            ]);
        }

        $user = $request->user();
        $existing = FirefliesIntegration::query()->where('user_id', $user->id)->first();

        $user->firefliesIntegration()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'api_key' => $validated['api_key'],
                'webhook_secret' => $validated['webhook_secret'] ?? null,
                'fireflies_email' => $email,
                'connected_at' => now(),
                'webhook_token' => data_get($existing, 'webhook_token')
                    ?? FirefliesIntegration::generateToken(),
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fireflies connected.']);

        return to_route('fireflies.edit');
    }

    /**
     * Rotate the webhook token, invalidating the old URL.
     */
    public function rotate(Request $request): RedirectResponse
    {
        $integration = $request->user()->firefliesIntegration;

        abort_if($integration === null, 404);

        $integration->update(['webhook_token' => FirefliesIntegration::generateToken()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Webhook URL rotated.']);

        return to_route('fireflies.edit');
    }

    /**
     * Disconnect Fireflies entirely.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->firefliesIntegration?->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fireflies disconnected.']);

        return to_route('fireflies.edit');
    }
}
