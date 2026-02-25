<?php

namespace App\Livewire;

use App\Models\Agent;
use Livewire\Component;

class ConnectionStatusWidget extends Component
{
    public Agent $agent;

    public string $state = 'waiting';

    public ?string $connectedAt = null;

    public ?string $agentStatus = null;

    public ?string $soulHashMatch = null;

    public ?string $lastCheckedAt = null;

    public function mount(Agent $agent): void
    {
        $this->agent = $agent;

        // If agent has already heartbeated, show connected
        if ($this->agent->last_heartbeat_at !== null) {
            $this->state = 'connected';
            $this->connectedAt = $this->agent->last_heartbeat_at->diffForHumans();
            $this->agentStatus = $this->agent->status->value;
            $this->lastCheckedAt = $this->agent->last_heartbeat_at->toIso8601String();
        }
    }

    public function checkHeartbeat(): void
    {
        $fresh = $this->agent->fresh();

        if (! $fresh || $fresh->last_heartbeat_at === null) {
            return;
        }

        $freshTimestamp = $fresh->last_heartbeat_at->toIso8601String();

        // Only update if heartbeat is newer than what we last saw
        if ($this->lastCheckedAt === $freshTimestamp) {
            return;
        }

        $this->lastCheckedAt = $freshTimestamp;
        $this->agent = $fresh;
        $this->agentStatus = $fresh->status->value;
        $this->connectedAt = $fresh->last_heartbeat_at->diffForHumans();

        if ($fresh->status->value === 'error') {
            $this->state = 'error';
        } else {
            $this->state = 'connected';
        }
    }

    /**
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        $teamId = auth()->user()?->current_team_id;

        if (! $teamId) {
            return [];
        }

        return [
            "echo-private:team.{$teamId},AgentHeartbeatReceived" => 'onHeartbeat',
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function onHeartbeat(array $event): void
    {
        if (($event['agentId'] ?? null) !== $this->agent->id) {
            return;
        }

        $status = $event['status'] ?? 'unknown';

        if ($status === 'error') {
            $this->state = 'error';
            $this->agentStatus = $status;
        } else {
            $this->state = 'connected';
            $this->agentStatus = $status;
        }

        $this->connectedAt = now()->format('g:i A');

        // Refresh agent to get latest data
        $this->agent->refresh();
        $this->soulHashMatch = $this->agent->soul_hash ? 'matched' : null;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $allAgents = Agent::query()
            ->orderByDesc('is_lead')
            ->orderBy('name')
            ->get();

        $unconfiguredAgents = $allAgents
            ->filter(fn (Agent $a) => $a->id !== $this->agent->id && $a->last_heartbeat_at === null);

        // Prioritize lead agent if it's not connected yet
        $nextAgent = $unconfiguredAgents->firstWhere('is_lead', true)
            ?? $unconfiguredAgents->first();

        $isMultiAgent = $allAgents->count() > 1;
        $totalAgents = $allAgents->count();

        return view('livewire.connection-status-widget', [
            'nextAgent' => $nextAgent,
            'unconfiguredAgents' => $unconfiguredAgents,
            'isMultiAgent' => $isMultiAgent,
            'totalAgents' => $totalAgents,
        ]);
    }
}
