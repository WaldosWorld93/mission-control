<?php

namespace App\Models;

use App\Enums\AgentStatus;
use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use BelongsToTeam, HasFactory, HasUuids;

    protected $fillable = [
        'team_id',
        'name',
        'role',
        'description',
        'soul_md',
        'soul_hash',
        'status',
        'is_lead',
        'skills',
        'heartbeat_model',
        'work_model',
        'api_token',
        'last_heartbeat_at',
        'last_task_completed_at',
        'metadata',
        'consecutive_errors',
        'is_paused',
        'paused_reason',
        'paused_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AgentStatus::class,
            'is_lead' => 'boolean',
            'skills' => 'array',
            'metadata' => 'array',
            'last_heartbeat_at' => 'datetime',
            'last_task_completed_at' => 'datetime',
            'consecutive_errors' => 'integer',
            'is_paused' => 'boolean',
            'paused_at' => 'datetime',
        ];
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withPivot('role_override', 'joined_at');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_agent_id');
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by_agent_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'from_agent_id');
    }

    public function heartbeats(): HasMany
    {
        return $this->hasMany(Heartbeat::class);
    }

    public function taskAttempts(): HasMany
    {
        return $this->hasMany(TaskAttempt::class);
    }

    public function scopeOnline($query)
    {
        return $query->where('status', AgentStatus::Online);
    }

    public function scopeOffline($query)
    {
        return $query->where('status', AgentStatus::Offline);
    }

    public function scopePaused($query)
    {
        return $query->where('is_paused', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_paused', false);
    }

    public function scopeLead($query)
    {
        return $query->where('is_lead', true);
    }
}
