<?php

namespace App\Models;

use App\Enums\SuggestionStatus;
use Database\Factories\TaskSuggestionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $meeting_id
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $due_date
 * @property SuggestionStatus $status
 * @property string|null $accepted_task_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TaskSuggestion extends Model
{
    /** @use HasFactory<TaskSuggestionFactory> */
    use HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'meeting_id',
        'title',
        'description',
        'due_date',
        'status',
        'accepted_task_id',
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
            'status' => SuggestionStatus::class,
        ];
    }

    /**
     * The meeting this suggestion was generated from.
     *
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * The task created when this suggestion was accepted.
     *
     * @return BelongsTo<Task, $this>
     */
    public function acceptedTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'accepted_task_id');
    }
}
