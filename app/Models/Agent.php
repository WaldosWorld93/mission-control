<?php

namespace App\Models;

use App\Enums\AgentStatus;
use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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

    public function getSlugAttribute(): string
    {
        return Str::slug($this->name);
    }

    public function getWorkspacePathAttribute(): string
    {
        if ($this->is_lead) {
            return '~/.openclaw/workspace';
        }

        return '~/.openclaw/workspace-'.$this->slug;
    }

    /**
     * @return array<string, mixed>
     */
    public function getToolsConfig(): array
    {
        $role = strtolower($this->role ?? '');

        // Lead/Orchestrator agents get full tool access
        if ($this->is_lead || Str::contains($role, ['lead', 'orchestrator'])) {
            return ['profile' => 'full'];
        }

        // Developer/coding agents get coding tools
        if (Str::contains($role, ['developer', 'coding', 'engineer', 'dev', 'qa', 'tester', 'testing', 'designer', 'devops', 'ops'])) {
            return ['profile' => 'coding'];
        }

        // Writer/editor/research agents get coding + web access
        if (Str::contains($role, ['writer', 'editor', 'research', 'analyst', 'knowledge'])) {
            return [
                'profile' => 'coding',
                'allow' => ['group:web'],
            ];
        }

        // Messaging/communication agents get messaging tools
        if (Str::contains($role, ['messaging', 'communication', 'comms', 'scheduler', 'monitor'])) {
            return ['profile' => 'messaging'];
        }

        // Default to full access
        return ['profile' => 'full'];
    }

    public function generateDefaultSoulMd(): string
    {
        $name = $this->name ?? 'Agent';
        $role = $this->role ?? 'Agent';
        $description = $this->description ?? 'An AI agent.';

        return <<<MD
        # {$name}

        ## Role
        {$role}

        ## Identity
        {$description}

        ## Working Style
        - Start each work session by checking for pending reviews and blocked tasks
        - Be concise and direct in all messages
        - Report blockers immediately rather than waiting
        - Update task status promptly as you make progress
        - Mark tasks complete only when acceptance criteria are fully met

        ## Communication
        - Use the messaging system for team coordination
        - Tag relevant team members when you need input
        - When reviewing, provide specific, actionable feedback

        ## Task Management
        - Only work on tasks assigned to you
        - If a task is unclear, ask for clarification before starting
        - Break large tasks into subtasks when appropriate
        MD;
    }
}
