<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'squad_template_id',
        'name',
        'role',
        'description',
        'soul_md_template',
        'is_lead',
        'heartbeat_model',
        'work_model',
        'skill_profile',
        'heartbeat_interval_seconds',
        'default_skills',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_lead' => 'boolean',
            'default_skills' => 'array',
            'heartbeat_interval_seconds' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function squadTemplate(): BelongsTo
    {
        return $this->belongsTo(SquadTemplate::class);
    }
}
