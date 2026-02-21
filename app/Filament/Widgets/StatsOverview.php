<?php

namespace App\Filament\Widgets;

use App\Enums\AgentStatus;
use App\Enums\TaskStatus;
use App\Models\Agent;
use App\Models\Project;
use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Agents Online', Agent::where('status', AgentStatus::Online)->count())
                ->description(Agent::count().' total agents')
                ->descriptionIcon('heroicon-o-cpu-chip')
                ->color('success'),

            Stat::make('Active Projects', Project::where('status', 'active')->count())
                ->description(Project::count().' total projects')
                ->descriptionIcon('heroicon-o-folder')
                ->color('primary'),

            Stat::make('Tasks In Progress', Task::where('status', TaskStatus::InProgress)->count())
                ->description(Task::whereIn('status', [TaskStatus::Backlog, TaskStatus::Assigned, TaskStatus::InProgress, TaskStatus::InReview, TaskStatus::Blocked])->count().' open tasks')
                ->descriptionIcon('heroicon-o-clipboard-document-list')
                ->color('info'),

            Stat::make('Tasks Completed', Task::where('status', TaskStatus::Done)->count())
                ->description('all time')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),
        ];
    }
}
