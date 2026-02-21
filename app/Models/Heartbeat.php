<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Heartbeat extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'agent_id',
        'team_id',
        'status_reported',
        'soul_hash_reported',
        'current_task_id',
        'ip_address',
        'metadata',
        'response_payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'response_payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function currentTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'current_task_id');
    }
}
