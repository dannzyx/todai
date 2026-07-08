<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the Fireflies GraphQL API. The API key is passed per call
 * so the client stays per-user and is easily mocked with Http::fake().
 *
 * @see https://docs.fireflies.ai/graphql-api/query/transcript
 */
class FirefliesClient
{
    protected const ENDPOINT = 'https://api.fireflies.ai/graphql';

    /**
     * Fetch a transcript by id, including its summary action items and sentences.
     *
     * @return array<string, mixed>|null The transcript payload, or null on failure.
     */
    public function transcript(string $apiKey, string $id): ?array
    {
        $query = <<<'GRAPHQL'
        query Transcript($id: String!) {
            transcript(id: $id) {
                title
                date
                summary { action_items overview }
                sentences { text speaker_name }
            }
        }
        GRAPHQL;

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->post(self::ENDPOINT, [
                'query' => $query,
                'variables' => ['id' => $id],
            ]);

        if ($response->failed()) {
            return null;
        }

        $transcript = $response->json('data.transcript');

        return is_array($transcript) ? $transcript : null;
    }

    /**
     * Verify an API key by fetching the account's email; null if invalid.
     */
    public function verifyEmail(string $apiKey): ?string
    {
        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->post(self::ENDPOINT, [
                'query' => 'query { user { email } }',
            ]);

        if ($response->failed()) {
            return null;
        }

        $email = $response->json('data.user.email');

        return is_string($email) ? $email : null;
    }
}
