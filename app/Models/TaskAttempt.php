<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'agent_id',
        'attempt_number',
        'started_at',
        'ended_at',
        'status',
        'result',
        'error_message',
        'token_usage',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'attempt_number' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'token_usage' => 'array',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
