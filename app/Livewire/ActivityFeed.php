<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\Agent;
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ActivityFeed extends Component
{
    use WithPagination;

    #[Url]
    public ?string $filterProject = null;

    #[Url]
    public ?string $filterAgent = null;

    #[Url]
    public ?string $filterEventType = null;

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
            "echo-private:team.{$teamId},AgentPaused" => '$refresh',
            "echo-private:team.{$teamId},TaskStatusChanged" => '$refresh',
            "echo-private:team.{$teamId},TaskUnblocked" => '$refresh',
            "echo-private:team.{$teamId},TaskClaimed" => '$refresh',
            "echo-private:team.{$teamId},MessageCreated" => '$refresh',
            "echo-private:team.{$teamId},ArtifactUploaded" => '$refresh',
            "echo-private:team.{$teamId},StuckTaskDetected" => '$refresh',
        ];
    }

    #[Computed]
    public function projects(): \Illuminate\Database\Eloquent\Collection
    {
        return Project::orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function agents(): \Illuminate\Database\Eloquent\Collection
    {
        return Agent::orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return array<string, string>
     */
    public static function eventTypes(): array
    {
        return [
            'agent.heartbeat' => 'Heartbeat',
            'agent.status_changed' => 'Status Change',
            'agent.paused' => 'Agent Paused',
            'task.status_changed' => 'Task Update',
            'task.unblocked' => 'Task Unblocked',
            'task.claimed' => 'Task Claimed',
            'task.stuck' => 'Stuck Task',
            'message.created' => 'Message',
            'artifact.uploaded' => 'Artifact',
        ];
    }

    public function clearFilters(): void
    {
        $this->filterProject = null;
        $this->filterAgent = null;
        $this->filterEventType = null;
        $this->resetPage();
    }

    public function updatedFilterProject(): void
    {
        $this->resetPage();
    }

    public function updatedFilterAgent(): void
    {
        $this->resetPage();
    }

    public function updatedFilterEventType(): void
    {
        $this->resetPage();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $query = ActivityLog::query()
            ->orderByDesc('created_at');

        if ($this->filterProject) {
            $query->where('project_id', $this->filterProject);
        }

        if ($this->filterAgent) {
            $query->where('actor_type', 'agent')
                ->where('actor_id', $this->filterAgent);
        }

        if ($this->filterEventType) {
            $query->where('event_type', $this->filterEventType);
        }

        return view('livewire.activity-feed', [
            'activities' => $query->paginate(50),
        ]);
    }
}
