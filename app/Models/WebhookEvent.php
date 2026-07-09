<?php

namespace App\Models;

use App\Enums\WebhookOutcome;
use Database\Factories\WebhookEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $source
 * @property string|null $user_id
 * @property WebhookOutcome $outcome
 * @property string|null $event_type
 * @property string|null $fireflies_meeting_id
 * @property bool $signed
 * @property string|null $ip
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'source',
        'user_id',
        'outcome',
        'event_type',
        'fireflies_meeting_id',
        'signed',
        'ip',
        'payload',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => WebhookOutcome::class,
            'signed' => 'boolean',
            'payload' => 'array',
        ];
    }

    /**
     * The user the delivery was attributed to, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
