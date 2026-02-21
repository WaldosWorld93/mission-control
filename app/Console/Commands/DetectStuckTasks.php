<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Events\StuckTaskDetected;
use App\Models\Task;
use Illuminate\Console\Command;

class DetectStuckTasks extends Command
{
    protected $signature = 'tasks:detect-stuck {--threshold=30 : Minutes since last heartbeat}';

    protected $description = 'Detect in-progress tasks whose assigned agent has gone silent';

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');

        $stuckTasks = Task::withoutGlobalScopes()
            ->where('status', TaskStatus::InProgress)
            ->whereHas('assignedAgent', fn ($q) => $q->where('last_heartbeat_at', '<', now()->subMinutes($threshold)))
            ->with(['assignedAgent', 'project'])
            ->get();

        if ($stuckTasks->isEmpty()) {
            $this->info('No stuck tasks detected.');

            return self::SUCCESS;
        }

        foreach ($stuckTasks as $task) {
            $minutesSince = (int) $task->assignedAgent->last_heartbeat_at->diffInMinutes(now());

            StuckTaskDetected::dispatch(
                $task->team_id,
                $task->id,
                $task->title,
                $task->assignedAgent->name,
                $minutesSince,
                $task->project_id,
            );

            $this->warn("Stuck: {$task->title} — {$task->assignedAgent->name} silent for {$minutesSince}min");
        }

        $this->info("Detected {$stuckTasks->count()} stuck task(s).");

        return self::SUCCESS;
    }
}
