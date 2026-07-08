<?php

namespace Database\Factories;

use App\Models\FirefliesIntegration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FirefliesIntegration>
 */
class FirefliesIntegrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'api_key' => 'ff-'.fake()->sha1(),
            'webhook_token' => FirefliesIntegration::generateToken(),
            'webhook_secret' => null,
            'fireflies_email' => fake()->safeEmail(),
            'connected_at' => now(),
        ];
    }

    /**
     * Indicate that the integration verifies webhook signatures.
     */
    public function withSecret(string $secret = 'shh-secret'): static
    {
        return $this->state(fn (array $attributes) => [
            'webhook_secret' => $secret,
        ]);
    }
}
