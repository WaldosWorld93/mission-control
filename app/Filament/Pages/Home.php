<?php

namespace App\Filament\Pages;

use App\Enums\AgentStatus;
use App\Enums\TaskStatus;
use App\Models\Agent;
use App\Models\Heartbeat;
use App\Models\Message;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskArtifact;
use Filament\Pages\Page;

class Home extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Home';

    protected static ?string $title = 'Home';

    protected static ?int $navigationSort = -2;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.home';

    protected static ?string $slug = 'home';

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
            "echo-private:team.{$teamId},StuckTaskDetected" => '$refresh',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $agents = Agent::query()
            ->with(['assignedTasks' => fn ($q) => $q->where('status', TaskStatus::InProgress)])
            ->orderByRaw("FIELD(status, 'online', 'busy', 'idle', 'error', 'offline')")
            ->get();

        $onlineCount = $agents->whereIn('status', [AgentStatus::Online, AgentStatus::Busy])->count();
        $totalAgents = $agents->count();

        return [
            'stats' => [
                'tasks_completed_today' => Task::where('status', TaskStatus::Done)
                    ->whereDate('completed_at', today())
                    ->count(),
                'active_projects' => Project::where('status', 'active')->count(),
                'agents_online' => $onlineCount,
                'agents_total' => $totalAgents,
                'open_tasks' => Task::whereIn('status', [
                    TaskStatus::Backlog, TaskStatus::Assigned,
                    TaskStatus::InProgress, TaskStatus::InReview, TaskStatus::Blocked,
                ])->count(),
            ],
            'agents' => $agents,
            'alerts' => $this->getAlerts($agents),
            'activityFeed' => $this->getActivityFeed(),
        ];
    }

    /**
     * @return array<string, \Illuminate\Support\Collection>
     */
    private function getAlerts(\Illuminate\Database\Eloquent\Collection $agents): array
    {
        $pausedAgents = $agents->where('is_paused', true);

        $offlineAgents = $agents
            ->where('status', AgentStatus::Offline)
            ->where('is_paused', false)
            ->filter(fn (Agent $a) => $a->last_heartbeat_at !== null);

        $stuckTasks = Task::where('status', TaskStatus::InProgress)
            ->whereHas('assignedAgent', fn ($q) => $q->where('last_heartbeat_at', '<', now()->subMinutes(30)))
            ->with(['assignedAgent', 'project'])
            ->limit(5)
            ->get();

        return [
            'paused' => $pausedAgents,
            'offline' => $offlineAgents,
            'stuck' => $stuckTasks,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function getActivityFeed(): \Illuminate\Support\Collection
    {
        $events = collect();

        // Recent heartbeats (last 2 hours, limit 10)
        Heartbeat::with('agent')
            ->where('created_at', '>=', now()->subHours(2))
            ->latest()
            ->limit(10)
            ->get()
            ->each(function ($hb) use ($events) {
                $events->push([
                    'type' => 'heartbeat',
                    'icon' => 'heroicon-o-signal',
                    'color' => 'sky',
                    'title' => ($hb->agent?->name ?? 'Unknown').' checked in',
                    'detail' => 'Status: '.$hb->status_reported,
                    'at' => $hb->created_at,
                ]);
            });

        // Recent task status changes (last 24h, limit 10)
        Task::with(['assignedAgent', 'project'])
            ->where('updated_at', '>=', now()->subDay())
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->each(function ($task) use ($events) {
                $events->push([
                    'type' => 'task',
                    'icon' => 'heroicon-o-clipboard-document-check',
                    'color' => match ($task->status) {
                        TaskStatus::Done => 'emerald',
                        TaskStatus::InProgress => 'sky',
                        TaskStatus::Blocked => 'rose',
                        default => 'slate',
                    },
                    'title' => $task->title,
                    'detail' => ($task->assignedAgent?->name ?? 'Unassigned').' — '.$task->status->value,
                    'at' => $task->updated_at,
                ]);
            });

        // Recent messages (last 24h, limit 10)
        Message::with('fromAgent')
            ->where('created_at', '>=', now()->subDay())
            ->latest()
            ->limit(10)
            ->get()
            ->each(function ($msg) use ($events) {
                $events->push([
                    'type' => 'message',
                    'icon' => 'heroicon-o-chat-bubble-left',
                    'color' => 'indigo',
                    'title' => ($msg->fromAgent?->name ?? 'User').' sent a message',
                    'detail' => \Illuminate\Support\Str::limit($msg->content, 80),
                    'at' => $msg->created_at,
                ]);
            });

        // Recent artifacts (last 24h, limit 5)
        TaskArtifact::with(['task', 'uploadedByAgent'])
            ->where('created_at', '>=', now()->subDay())
            ->latest()
            ->limit(5)
            ->get()
            ->each(function ($artifact) use ($events) {
                $events->push([
                    'type' => 'artifact',
                    'icon' => 'heroicon-o-paper-clip',
                    'color' => 'violet',
                    'title' => ($artifact->uploadedByAgent?->name ?? 'User').' uploaded '.$artifact->display_name,
                    'detail' => 'on '.$artifact->task?->title,
                    'at' => $artifact->created_at,
                ]);
            });

        return $events->sortByDesc('at')->take(20)->values();
    }
}
