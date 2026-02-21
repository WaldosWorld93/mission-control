<?php

namespace App\Events;

use App\Events\Concerns\LogsActivity;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, LogsActivity, SerializesModels;

    public function __construct(
        public int $teamId,
        public string $taskId,
        public string $taskTitle,
        public string $oldStatus,
        public string $newStatus,
        public ?string $agentName = null,
        public ?string $projectId = null,
    ) {
        $this->logActivity();
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("team.{$this->teamId}")];
    }

    protected function eventType(): string
    {
        return 'task.status_changed';
    }

    protected function actorType(): string
    {
        return 'agent';
    }

    protected function actorId(): ?string
    {
        return null;
    }

    protected function activityDescription(): string
    {
        $agent = $this->agentName ? " by {$this->agentName}" : '';

        return "{$this->taskTitle} moved to {$this->newStatus}{$agent}";
    }

    protected function activityMetadata(): ?array
    {
        return [
            'task_id' => $this->taskId,
            'task_title' => $this->taskTitle,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'agent_name' => $this->agentName,
        ];
    }

    protected function projectId(): ?string
    {
        return $this->projectId;
    }
}
