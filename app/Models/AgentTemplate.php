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
        'default_skills',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_lead' => 'boolean',
            'default_skills' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function squadTemplate(): BelongsTo
    {
        return $this->belongsTo(SquadTemplate::class);
    }
}
