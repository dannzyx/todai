<?php

namespace App\Models;

use App\Enums\MeetingSource;
use App\Enums\MeetingStatus;
use App\Enums\SuggestionConfidence;
use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property MeetingSource $source
 * @property string|null $fireflies_meeting_id
 * @property string|null $title
 * @property Carbon|null $meeting_date
 * @property string|null $notes
 * @property string|null $summary
 * @property string|null $action_items
 * @property string|null $transcript
 * @property string|null $project_id
 * @property string|null $suggested_project_id
 * @property string|null $suggested_project_name
 * @property SuggestionConfidence|null $suggestion_confidence
 * @property string|null $suggestion_reasoning
 * @property string|null $language
 * @property MeetingStatus $status
 * @property string|null $error
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Meeting extends Model
{
    /** @use HasFactory<MeetingFactory> */
    use HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'source',
        'fireflies_meeting_id',
        'title',
        'meeting_date',
        'notes',
        'summary',
        'action_items',
        'transcript',
        'project_id',
        'suggested_project_id',
        'suggested_project_name',
        'suggestion_confidence',
        'suggestion_reasoning',
        'language',
        'status',
        'error',
        'processed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => MeetingSource::class,
            'meeting_date' => 'datetime',
            'processed_at' => 'datetime',
            'status' => MeetingStatus::class,
            'suggestion_confidence' => SuggestionConfidence::class,
        ];
    }

    /**
     * The user that owns the meeting.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The tasks accepted from this meeting.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * The staged todo suggestions generated for this meeting.
     *
     * @return HasMany<TaskSuggestion, $this>
     */
    public function taskSuggestions(): HasMany
    {
        return $this->hasMany(TaskSuggestion::class);
    }

    /**
     * The project the meeting's tasks are filed under, once resolved.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The existing project the AI suggested for this meeting.
     *
     * @return BelongsTo<Project, $this>
     */
    public function suggestedProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'suggested_project_id');
    }

    /**
     * Determine whether the meeting has a pending project suggestion.
     */
    public function hasProjectSuggestion(): bool
    {
        return $this->suggested_project_id !== null || $this->suggested_project_name !== null;
    }
}
