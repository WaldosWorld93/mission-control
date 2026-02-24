<?php

namespace App\Livewire;

use App\Models\Agent;
use Livewire\Component;

class SquadProgressBar extends Component
{
    public ?string $currentAgentId = null;

    public function mount(?Agent $currentAgent = null): void
    {
        $this->currentAgentId = $currentAgent?->id;
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
            "echo-private:team.{$teamId},AgentHeartbeatReceived" => '$refresh',
            "echo-private:team.{$teamId},AgentStatusChanged" => '$refresh',
        ];
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $agents = Agent::query()->orderBy('name')->get();

        return view('livewire.squad-progress-bar', [
            'agents' => $agents,
        ]);
    }
}
