<?php

namespace App\Events\Concerns;

use App\Models\ActivityLog;

trait LogsActivity
{
    public function logActivity(): void
    {
        ActivityLog::create([
            'team_id' => $this->teamId,
            'event_type' => $this->eventType(),
            'actor_type' => $this->actorType(),
            'actor_id' => $this->actorId(),
            'description' => $this->activityDescription(),
            'metadata' => $this->activityMetadata(),
            'project_id' => $this->projectId(),
            'created_at' => now(),
        ]);
    }

    abstract protected function eventType(): string;

    abstract protected function actorType(): string;

    abstract protected function actorId(): ?string;

    abstract protected function activityDescription(): string;

    protected function activityMetadata(): ?array
    {
        return null;
    }

    protected function projectId(): ?string
    {
        return null;
    }
}
