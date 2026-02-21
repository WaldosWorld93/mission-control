<?php

namespace App\Models;

use App\Enums\ArtifactType;
use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskArtifact extends Model
{
    use BelongsToTeam, HasFactory, HasUuids;

    protected $fillable = [
        'task_id',
        'team_id',
        'filename',
        'display_name',
        'mime_type',
        'size_bytes',
        'storage_path',
        'content_text',
        'artifact_type',
        'version',
        'uploaded_by_agent_id',
        'uploaded_by_user_id',
        'metadata',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'artifact_type' => ArtifactType::class,
            'size_bytes' => 'integer',
            'version' => 'integer',
            'metadata' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploadedByAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'uploaded_by_agent_id');
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
