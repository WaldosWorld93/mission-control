<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AgentStatus;
use App\Enums\AttemptStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Events\AgentHeartbeatReceived;
use App\Events\AgentPaused;
use App\Events\AgentStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\HeartbeatRequest;
use App\Models\Heartbeat;
use App\Models\Message;
use App\Models\Task;
use Illuminate\Http\JsonResponse;

class HeartbeatController extends Controller
{
    public function store(HeartbeatRequest $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $validated = $request->validated();

        // Process error / circuit breaker
        if (! empty($validated['error'])) {
            $agent->increment('consecutive_errors');
            $agent->refresh();

            // Handle task failure if error references a task
            if (! empty($validated['error']['task_id'])) {
                $errorTask = Task::find($validated['error']['task_id']);

                if ($errorTask) {
                    $activeAttempt = $errorTask->attempts()
                        ->where('status', AttemptStatus::Active)
                        ->latest()
                        ->first();

                    if ($activeAttempt) {
                        $activeAttempt->update([
                            'ended_at' => now(),
                            'status' => AttemptStatus::Failed,
                            'error_message' => $validated['error']['message'] ?? null,
                        ]);
                    }
                }
            }

            // Circuit breaker: 3 consecutive errors → pause
            if ($agent->consecutive_errors >= 3) {
                $agent->update([
                    'is_paused' => true,
                    'paused_reason' => 'circuit_breaker',
                    'paused_at' => now(),
                ]);

                AgentPaused::dispatch(
                    $agent->team_id,
                    $agent->id,
                    $agent->name,
                    'circuit_breaker',
                    $agent->consecutive_errors,
                );

                return response()->json([
                    'status' => 'paused',
                    'reason' => 'circuit_breaker',
                ]);
            }
        } else {
            // Successful heartbeat resets errors
            if ($agent->consecutive_errors > 0) {
                $agent->update(['consecutive_errors' => 0]);
            }
        }

        // Update agent status and heartbeat time
        $oldStatus = $agent->status;
        $newStatus = AgentStatus::tryFrom($validated['status'] ?? '') ?? $agent->status;

        $agent->update([
            'status' => $newStatus,
            'last_heartbeat_at' => now(),
        ]);

        // Broadcast status change if different
        if ($oldStatus !== $newStatus) {
            AgentStatusChanged::dispatch(
                $agent->team_id,
                $agent->id,
                $agent->name,
                $oldStatus->value,
                $newStatus->value,
            );
        }

        // Build assigned tasks (never blocked)
        $tasks = $agent->assignedTasks()
            ->where('status', '!=', TaskStatus::Blocked)
            ->whereIn('status', [TaskStatus::Assigned, TaskStatus::InProgress, TaskStatus::InReview])
            ->with(['project:id,name', 'parent:id,title'])
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'project' => $task->project?->name,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status->value,
                'priority' => $task->priority->value,
                'created_by' => $task->created_by_agent_id,
                'subtask_of' => $task->parent_task_id,
                'previous_attempts' => $task->attempts()->count() - 1,
            ]);

        // Blocked summary
        $blockedTasks = $agent->assignedTasks()
            ->where('status', TaskStatus::Blocked)
            ->with('dependencies:id,title,status')
            ->get();

        $blockedSummary = [
            'count' => $blockedTasks->count(),
            'next_up' => $blockedTasks->take(3)->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'waiting_on' => $task->dependencies
                    ->filter(fn (Task $dep) => $dep->status !== TaskStatus::Done)
                    ->pluck('title')
                    ->values()
                    ->all(),
            ])->values()->all(),
        ];

        // Soul sync check
        $soulSync = null;
        if (isset($validated['soul_hash']) && $validated['soul_hash'] !== $agent->soul_hash) {
            $soulSync = [
                'soul_md' => $agent->soul_md,
                'soul_hash' => $agent->soul_hash,
            ];
        }

        // Build notifications from unread @mentions
        $unreadMentions = Message::whereJsonContains('mentions', $agent->id)
            ->whereJsonDoesntContain('read_by', $agent->id)
            ->with(['fromAgent:id,name', 'thread:id,subject,task_id'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $notifications = [];

        if ($unreadMentions->isNotEmpty()) {
            // Batch-load thread context
            $threadIds = $unreadMentions->pluck('thread_id')->unique()->filter();
            $threadMessages = Message::whereIn('thread_id', $threadIds)
                ->with('fromAgent:id,name')
                ->orderBy('sequence_in_thread')
                ->get()
                ->groupBy('thread_id');

            foreach ($unreadMentions as $mention) {
                $allInThread = $threadMessages->get($mention->thread_id, collect());

                if ($allInThread->count() <= 21) {
                    $threadContext = $allInThread->map(fn (Message $m) => [
                        'sequence' => $m->sequence_in_thread,
                        'from' => $m->fromAgent?->name,
                        'content' => $m->content,
                        'created_at' => $m->created_at->toISOString(),
                    ])->values()->all();
                } else {
                    $first = $allInThread->first();
                    $last20 = $allInThread->slice(-20);
                    $context = collect([$first])->merge($last20)->unique('id');

                    $threadContext = $context->map(fn (Message $m) => [
                        'sequence' => $m->sequence_in_thread,
                        'from' => $m->fromAgent?->name,
                        'content' => $m->content,
                        'created_at' => $m->created_at->toISOString(),
                    ])->values()->all();
                }

                $notifications[] = [
                    'id' => $mention->id,
                    'type' => 'mention',
                    'from' => $mention->fromAgent?->name,
                    'content' => $mention->content,
                    'thread_id' => $mention->thread_id,
                    'thread_subject' => $mention->thread?->subject,
                    'linked_task_id' => $mention->thread?->task_id,
                    'thread_context' => $threadContext,
                ];
            }

            // Mark mentions as read
            foreach ($unreadMentions as $mention) {
                $readBy = $mention->read_by ?? [];
                $readBy[] = $agent->id;
                $mention->update(['read_by' => array_values(array_unique($readBy))]);
            }
        }

        // Adaptive heartbeat interval
        $heartbeatInterval = $tasks->isNotEmpty() ? 120 : 300;

        // Active projects
        $activeProjects = $agent->projects()
            ->where('status', ProjectStatus::Active)
            ->pluck('name')
            ->all();

        $responsePayload = [
            'status' => 'ok',
            'notifications' => $notifications,
            'tasks' => $tasks->values()->all(),
            'blocked_summary' => $blockedSummary,
            'soul_sync' => $soulSync,
            'config' => [
                'heartbeat_interval_seconds' => $heartbeatInterval,
                'active_projects' => $activeProjects,
            ],
        ];

        // Broadcast heartbeat received
        $currentTask = $tasks->first();
        AgentHeartbeatReceived::dispatch(
            $agent->team_id,
            $agent->id,
            $agent->name,
            $newStatus->value,
            $currentTask['title'] ?? null,
        );

        // Log heartbeat
        Heartbeat::create([
            'agent_id' => $agent->id,
            'team_id' => $agent->team_id,
            'status_reported' => $validated['status'] ?? $agent->status->value,
            'soul_hash_reported' => $validated['soul_hash'] ?? null,
            'current_task_id' => $validated['current_task_id'] ?? null,
            'ip_address' => $request->ip(),
            'metadata' => $validated['metadata'] ?? null,
            'response_payload' => $responsePayload,
            'created_at' => now(),
        ]);

        return response()->json($responsePayload);
    }
}
