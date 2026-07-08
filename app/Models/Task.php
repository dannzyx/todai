<?php

namespace App\Models;

use App\Enums\SuggestionConfidence;
use App\Enums\TaskSource;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $project_id
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $due_date
 * @property Carbon|null $completed_at
 * @property TaskSource $source
 * @property string|null $meeting_import_id
 * @property string|null $suggested_project_id
 * @property SuggestionConfidence|null $suggestion_confidence
 * @property string|null $suggestion_reasoning
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'project_id',
        'title',
        'description',
        'due_date',
        'completed_at',
        'source',
        'meeting_import_id',
        'suggested_project_id',
        'suggestion_confidence',
        'suggestion_reasoning',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'source' => TaskSource::class,
            'suggestion_confidence' => SuggestionConfidence::class,
        ];
    }

    /**
     * The user that owns the task.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The project the task belongs to (null = Inbox).
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The project the AI suggested for this task.
     *
     * @return BelongsTo<Project, $this>
     */
    public function suggestedProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'suggested_project_id');
    }

    /**
     * The meeting import this task originated from.
     *
     * @return BelongsTo<MeetingImport, $this>
     */
    public function meetingImport(): BelongsTo
    {
        return $this->belongsTo(MeetingImport::class);
    }

    /**
     * Scope a query to only include incomplete tasks.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeIncomplete(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    /**
     * Scope a query to only include Inbox tasks (no project).
     *
     * @param  Builder<Task>  $query
     */
    public function scopeInInbox(Builder $query): void
    {
        $query->whereNull('project_id');
    }

    /**
     * Determine whether the task is completed.
     */
    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Determine whether the task lives in the Inbox.
     */
    public function isInInbox(): bool
    {
        return $this->project_id === null;
    }

    /**
     * Determine whether the task has a pending AI project suggestion.
     */
    public function hasSuggestion(): bool
    {
        return $this->suggested_project_id !== null;
    }
}
