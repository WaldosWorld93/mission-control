<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AttemptStatus;
use App\Enums\DependencyType;
use App\Enums\MessageType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Events\TaskClaimed;
use App\Events\TaskStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateTaskRequest;
use App\Http\Requests\Api\V1\ListTasksRequest;
use App\Http\Requests\Api\V1\UpdateTaskRequest;
use App\Jobs\ResolveDependencies;
use App\Models\Agent;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Task;
use App\Services\MentionParser;
use App\StateMachines\TaskStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function index(ListTasksRequest $request): JsonResponse
    {
        $agent = app('agent');

        $query = Task::query()
            ->where('status', '!=', TaskStatus::Blocked);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->input('assigned_to') === 'me') {
            $query->where('assigned_agent_id', $agent->id);
        }

        $tasks = $query->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json(['data' => $tasks]);
    }

    public function store(CreateTaskRequest $request): JsonResponse
    {
        $agent = app('agent');
        $validated = $request->validated();

        // Validate parent task depth
        if (! empty($validated['parent_task_id'])) {
            $parent = Task::find($validated['parent_task_id']);

            if (! $parent) {
                return response()->json(['message' => 'Parent task not found.'], 422);
            }

            if ($parent->depth >= 2) {
                return response()->json([
                    'message' => 'Maximum task nesting depth (2) exceeded.',
                ], 422);
            }
        }

        // Validate cross-project dependencies
        if (! empty($validated['depends_on'])) {
            $depTasks = Task::whereIn('id', $validated['depends_on'])->get();

            foreach ($depTasks as $depTask) {
                if ($depTask->project_id !== $validated['project_id']) {
                    return response()->json([
                        'message' => 'Cross-project dependencies are not allowed.',
                    ], 422);
                }
            }
        }

        // Resolve assigned agent by name
        $assignedAgentId = null;
        if (! empty($validated['assigned_agent_name'])) {
            $assignedAgent = Agent::where('name', $validated['assigned_agent_name'])->first();

            if (! $assignedAgent) {
                return response()->json([
                    'message' => "Agent '{$validated['assigned_agent_name']}' not found.",
                ], 422);
            }

            $assignedAgentId = $assignedAgent->id;
        }

        // Determine initial status
        $hasDependencies = ! empty($validated['depends_on']);
        $initialStatus = $hasDependencies ? TaskStatus::Blocked : TaskStatus::Backlog;

        if (! $hasDependencies && $assignedAgentId) {
            $initialStatus = TaskStatus::Assigned;
        }

        $task = Task::create([
            'project_id' => $validated['project_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $initialStatus,
            'priority' => TaskPriority::tryFrom($validated['priority'] ?? '') ?? TaskPriority::Medium,
            'assigned_agent_id' => $assignedAgentId,
            'created_by_agent_id' => $agent->id,
            'parent_task_id' => $validated['parent_task_id'] ?? null,
            'depth' => isset($validated['parent_task_id'])
                ? (Task::find($validated['parent_task_id'])->depth + 1)
                : 0,
            'tags' => $validated['tags'] ?? [],
            'claimed_at' => $assignedAgentId ? now() : null,
        ]);

        // Attach dependencies
        if (! empty($validated['depends_on'])) {
            foreach ($validated['depends_on'] as $depId) {
                $task->dependencies()->attach($depId, [
                    'dependency_type' => DependencyType::FinishToStart->value,
                ]);
            }

            // Check if deps are already met
            $this->checkAndResolveDependencies($task);
        }

        // Create attempt if assigned
        if ($assignedAgentId && $task->status === TaskStatus::Assigned) {
            $task->attempts()->create([
                'agent_id' => $assignedAgentId,
                'attempt_number' => 1,
                'started_at' => now(),
                'status' => AttemptStatus::Active,
            ]);
        }

        // Create initial message thread if provided
        if (! empty($validated['initial_message'])) {
            $mentionParser = app(MentionParser::class);
            $mentions = $mentionParser->parse($validated['initial_message']);

            $thread = MessageThread::create([
                'project_id' => $task->project_id,
                'task_id' => $task->id,
                'subject' => $task->title,
                'started_by_agent_id' => $agent->id,
                'message_count' => 1,
            ]);

            Message::create([
                'project_id' => $task->project_id,
                'from_agent_id' => $agent->id,
                'thread_id' => $thread->id,
                'sequence_in_thread' => 1,
                'content' => $validated['initial_message'],
                'mentions' => $mentions['agent_ids'],
                'read_by' => [$agent->id],
                'message_type' => MessageType::TaskUpdate,
            ]);
        }

        return response()->json(['data' => $task->fresh()], 201);
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $agent = app('agent');
        $validated = $request->validated();
        $newStatus = null;
        $oldStatus = $task->status;

        if (isset($validated['status'])) {
            $newStatus = TaskStatus::from($validated['status']);

            // Agents cannot transition out of system-only statuses
            if (TaskStateMachine::isSystemOnly($task->status)) {
                return response()->json([
                    'message' => "Cannot transition from '{$task->status->value}' — system-managed status.",
                ], 422);
            }

            if (! TaskStateMachine::canTransition($task->status, $newStatus)) {
                return response()->json([
                    'message' => "Invalid transition from '{$task->status->value}' to '{$newStatus->value}'.",
                ], 422);
            }

            $task->status = $newStatus;

            // Handle lifecycle timestamps
            if ($newStatus === TaskStatus::InProgress && ! $task->started_at) {
                $task->started_at = now();
            }

            if (in_array($newStatus, [TaskStatus::Done, TaskStatus::Cancelled])) {
                $task->completed_at = now();
            }
        }

        if (isset($validated['result'])) {
            $task->result = $validated['result'];
        }

        if (isset($validated['assigned_agent_name'])) {
            $assignedAgent = Agent::where('name', $validated['assigned_agent_name'])->first();

            if (! $assignedAgent) {
                return response()->json([
                    'message' => "Agent '{$validated['assigned_agent_name']}' not found.",
                ], 422);
            }

            $task->assigned_agent_id = $assignedAgent->id;
        }

        $task->save();

        // Post-save side effects for status transitions
        if ($newStatus) {
            if (in_array($newStatus, [TaskStatus::Done, TaskStatus::Cancelled])) {
                $activeAttempt = $task->attempts()
                    ->where('status', AttemptStatus::Active)
                    ->latest()
                    ->first();

                if ($activeAttempt) {
                    $activeAttempt->update([
                        'ended_at' => now(),
                        'status' => $newStatus === TaskStatus::Done
                            ? AttemptStatus::Completed
                            : AttemptStatus::Failed,
                    ]);
                }
            }

            if (in_array($newStatus, [TaskStatus::Done, TaskStatus::InReview])) {
                $task->dependents()->each(function (Task $dependent) {
                    ResolveDependencies::dispatch($dependent->id);
                });
            }

            if ($newStatus === TaskStatus::Done && $task->parent_task_id) {
                $this->checkParentCompletion($task);
            }

            TaskStatusChanged::dispatch(
                $task->team_id,
                $task->id,
                $task->title,
                $oldStatus->value,
                $newStatus->value,
                $agent->name,
                $task->project_id,
            );
        }

        return response()->json(['data' => $task->fresh()]);
    }

    public function claim(Task $task): JsonResponse
    {
        $agent = app('agent');

        $affected = DB::table('tasks')
            ->where('id', $task->id)
            ->where('status', TaskStatus::Backlog->value)
            ->whereNull('assigned_agent_id')
            ->update([
                'assigned_agent_id' => $agent->id,
                'status' => TaskStatus::Assigned->value,
                'claimed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            return response()->json([
                'message' => 'Task is not available for claiming.',
            ], 409);
        }

        $task->refresh();

        // Create attempt
        $attemptNumber = $task->attempts()->count() + 1;
        $task->attempts()->create([
            'agent_id' => $agent->id,
            'attempt_number' => $attemptNumber,
            'started_at' => now(),
            'status' => AttemptStatus::Active,
        ]);

        TaskClaimed::dispatch(
            $task->team_id,
            $task->id,
            $task->title,
            $agent->name,
            $task->project_id,
        );

        return response()->json(['data' => $task]);
    }

    private function checkAndResolveDependencies(Task $task): void
    {
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

        if ($allMet) {
            $task->update([
                'status' => $task->assigned_agent_id ? TaskStatus::Assigned : TaskStatus::Backlog,
            ]);
        }
    }

    private function checkParentCompletion(Task $task): void
    {
        $parent = $task->parent;

        if (! $parent) {
            return;
        }

        $allSubtasksDone = $parent->subtasks()
            ->where('status', '!=', TaskStatus::Done)
            ->doesntExist();

        if ($allSubtasksDone && TaskStateMachine::canTransition($parent->status, TaskStatus::Done)) {
            $parent->update([
                'status' => TaskStatus::Done,
                'completed_at' => now(),
            ]);

            // Cascade: resolve dependents of parent too
            $parent->dependents()->each(function (Task $dependent) {
                ResolveDependencies::dispatch($dependent->id);
            });
        }
    }
}
