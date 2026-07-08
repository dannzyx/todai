<?php

namespace App\Models;

use Database\Factories\FirefliesIntegrationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $user_id
 * @property string $api_key
 * @property string $webhook_token
 * @property string|null $webhook_secret
 * @property string|null $fireflies_email
 * @property Carbon|null $connected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FirefliesIntegration extends Model
{
    /** @use HasFactory<FirefliesIntegrationFactory> */
    use HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'api_key',
        'webhook_token',
        'webhook_secret',
        'fireflies_email',
        'connected_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'connected_at' => 'datetime',
        ];
    }

    /**
     * The user that owns the integration.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a fresh, unguessable webhook token.
     */
    public static function generateToken(): string
    {
        return Str::random(48);
    }
}
