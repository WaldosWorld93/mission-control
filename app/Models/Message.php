<?php

namespace App\Models;

use App\Enums\MessageType;
use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use BelongsToTeam, HasFactory, HasUuids;

    protected $fillable = [
        'team_id',
        'project_id',
        'from_agent_id',
        'from_user_id',
        'thread_id',
        'sequence_in_thread',
        'content',
        'mentions',
        'read_by',
        'message_type',
    ];

    protected function casts(): array
    {
        return [
            'message_type' => MessageType::class,
            'mentions' => 'array',
            'read_by' => 'array',
            'sequence_in_thread' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function fromAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'from_agent_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'thread_id');
    }
}
