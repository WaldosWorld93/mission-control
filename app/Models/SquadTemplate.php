<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SquadTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'use_case',
        'agent_configs',
        'is_public',
        'estimated_daily_cost',
        'created_by_team_id',
    ];

    protected function casts(): array
    {
        return [
            'agent_configs' => 'array',
            'is_public' => 'boolean',
            'estimated_daily_cost' => 'decimal:2',
        ];
    }

    public function createdByTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'created_by_team_id');
    }

    public function agentTemplates(): HasMany
    {
        return $this->hasMany(AgentTemplate::class, 'squad_template_id')->orderBy('sort_order');
    }
}
