<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use BelongsToTeam, HasFactory, HasUuids;

    protected $fillable = [
        'team_id',
        'name',
        'slug',
        'description',
        'status',
        'color',
        'lead_agent_id',
        'settings',
        'started_at',
        'completed_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'settings' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function leadAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'lead_agent_id');
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class)->withPivot('role_override', 'joined_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function messageThreads(): HasMany
    {
        return $this->hasMany(MessageThread::class);
    }
}
