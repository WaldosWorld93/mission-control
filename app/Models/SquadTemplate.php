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
        'created_by_team_id',
    ];

    protected function casts(): array
    {
        return [
            'agent_configs' => 'array',
            'is_public' => 'boolean',
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
