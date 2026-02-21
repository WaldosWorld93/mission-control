<?php

namespace App\Events;

use App\Events\Concerns\LogsActivity;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StuckTaskDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, LogsActivity, SerializesModels;

    public function __construct(
        public int $teamId,
        public string $taskId,
        public string $taskTitle,
        public string $agentName,
        public int $minutesSinceHeartbeat,
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
        return 'task.stuck';
    }

    protected function actorType(): string
    {
        return 'system';
    }

    protected function actorId(): ?string
    {
        return null;
    }

    protected function activityDescription(): string
    {
        return "{$this->taskTitle} appears stuck — {$this->agentName} silent for {$this->minutesSinceHeartbeat}min";
    }

    protected function activityMetadata(): ?array
    {
        return [
            'task_id' => $this->taskId,
            'task_title' => $this->taskTitle,
            'agent_name' => $this->agentName,
            'minutes_since_heartbeat' => $this->minutesSinceHeartbeat,
        ];
    }

    protected function projectId(): ?string
    {
        return $this->projectId;
    }
}
