<?php

namespace App\Events;

use App\Events\Concerns\LogsActivity;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentPaused implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, LogsActivity, SerializesModels;

    public function __construct(
        public int $teamId,
        public string $agentId,
        public string $agentName,
        public string $reason,
        public int $consecutiveErrors,
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
        return 'agent.paused';
    }

    protected function actorType(): string
    {
        return 'system';
    }

    protected function actorId(): ?string
    {
        return $this->agentId;
    }

    protected function activityDescription(): string
    {
        return "{$this->agentName} paused — {$this->reason}";
    }

    protected function activityMetadata(): ?array
    {
        return [
            'agent_name' => $this->agentName,
            'reason' => $this->reason,
            'consecutive_errors' => $this->consecutiveErrors,
        ];
    }
}
