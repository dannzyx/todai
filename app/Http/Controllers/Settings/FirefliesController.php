<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\FirefliesIntegration;
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
                'api_key' => 'Deze API-sleutel is ongeldig of Fireflies is niet bereikbaar.',
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

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fireflies gekoppeld.']);

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

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Webhook-URL vernieuwd.']);

        return to_route('fireflies.edit');
    }

    /**
     * Disconnect Fireflies entirely.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->firefliesIntegration?->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fireflies ontkoppeld.']);

        return to_route('fireflies.edit');
    }
}
