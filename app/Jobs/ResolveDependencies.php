<?php

namespace App\Jobs;

use App\Enums\AttemptStatus;
use App\Enums\DependencyType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\TeamContext;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ResolveDependencies implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public string $dependentTaskId) {}

    public function uniqueId(): string
    {
        return $this->dependentTaskId;
    }

    public function handle(): void
    {
        $task = Task::withoutGlobalScopes()->find($this->dependentTaskId);

        if (! $task || $task->status !== TaskStatus::Blocked) {
            return;
        }

        // Set team context for subsequent queries
        app(TeamContext::class)->set($task->team);

        $allMet = true;

        foreach ($task->dependencies()->withPivot('dependency_type')->get() as $dep) {
            $type = DependencyType::from($dep->pivot->dependency_type);

            if ($type === DependencyType::FinishToStart && $dep->status !== TaskStatus::Done) {
                $allMet = false;
                break;
            }

            if ($type === DependencyType::FinishToReview
                && ! in_array($dep->status, [TaskStatus::InReview, TaskStatus::Done])) {
                $allMet = false;
                break;
            }
        }

        if (! $allMet) {
            return;
        }

        $newStatus = $task->assigned_agent_id ? TaskStatus::Assigned : TaskStatus::Backlog;
        $task->update(['status' => $newStatus]);

        // Create attempt if transitioning to assigned
        if ($newStatus === TaskStatus::Assigned && $task->assigned_agent_id) {
            $attemptNumber = $task->attempts()->count() + 1;
            $task->attempts()->create([
                'agent_id' => $task->assigned_agent_id,
                'attempt_number' => $attemptNumber,
                'started_at' => now(),
                'status' => AttemptStatus::Active,
            ]);
        }
    }
}
