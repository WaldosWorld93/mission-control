<?php

namespace App\Livewire;

use App\Models\Agent;
use Livewire\Component;

class SquadChecklistWidget extends Component
{
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
        $agents = Agent::query()
            ->orderByDesc('is_lead')
            ->orderBy('name')
            ->get();

        $total = $agents->count();
        $connected = $agents->filter(fn (Agent $a) => $a->last_heartbeat_at !== null)->count();
        $allConnected = $total > 0 && $connected === $total;

        $leadAgent = $agents->firstWhere('is_lead', true);
        $leadConnected = $leadAgent && $leadAgent->last_heartbeat_at !== null;

        return view('livewire.squad-checklist-widget', [
            'agents' => $agents,
            'total' => $total,
            'connected' => $connected,
            'allConnected' => $allConnected,
            'leadAgent' => $leadAgent,
            'leadConnected' => $leadConnected,
        ]);
    }
}
