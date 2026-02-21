<?php

namespace App\Livewire;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\StateMachines\TaskStateMachine;
use Filament\Notifications\Notification;
use Livewire\Attributes\Computed;
use Livewire\Component;

class KanbanBoard extends Component
{
    public Project $project;

    public ?string $filterAgent = null;

    public ?string $filterPriority = null;

    public ?string $filterTag = null;

    public ?string $selectedTaskId = null;

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
            "echo-private:team.{$teamId},TaskStatusChanged" => '$refresh',
            "echo-private:team.{$teamId},TaskClaimed" => '$refresh',
            "echo-private:team.{$teamId},TaskUnblocked" => '$refresh',
        ];
    }

    /** @var array<string, string> */
    public const COLUMN_COLORS = [
        'blocked' => 'rose',
        'backlog' => 'slate',
        'assigned' => 'indigo',
        'in_progress' => 'sky',
        'in_review' => 'violet',
        'done' => 'emerald',
    ];

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function columns(): array
    {
        return [
            'blocked' => ['Blocked', 'rose'],
            'backlog' => ['Backlog', 'slate'],
            'assigned' => ['Assigned', 'indigo'],
            'in_progress' => ['In Progress', 'sky'],
            'in_review' => ['In Review', 'violet'],
            'done' => ['Done', 'emerald'],
        ];
    }

    #[Computed]
    public function tasks(): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->project->tasks()
            ->with(['assignedAgent', 'dependencies', 'subtasks', 'parent'])
            ->whereNull('parent_task_id');

        if ($this->filterAgent) {
            $query->where('assigned_agent_id', $this->filterAgent);
        }

        if ($this->filterPriority) {
            $query->where('priority', $this->filterPriority);
        }

        if ($this->filterTag) {
            $query->whereJsonContains('tags', $this->filterTag);
        }

        return $query->orderBy('sort_order')->get();
    }

    #[Computed]
    public function agents(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->project->agents()->orderBy('name')->get();
    }

    #[Computed]
    public function allTags(): array
    {
        return $this->project->tasks()
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function moveTask(string $taskId, string $newStatus): void
    {
        $task = Task::find($taskId);

        if (! $task || $task->project_id !== $this->project->id) {
            return;
        }

        $targetStatus = TaskStatus::from($newStatus);

        if ($task->status === $targetStatus) {
            return;
        }

        if (TaskStateMachine::isSystemOnly($task->status)) {
            Notification::make()
                ->title('Invalid move')
                ->body("Tasks in \"{$task->status->value}\" status can only be moved by the system.")
                ->danger()
                ->send();

            $this->dispatch('kanban-refresh');

            return;
        }

        if (! TaskStateMachine::canTransition($task->status, $targetStatus)) {
            $allowed = collect(TaskStateMachine::allowedTransitions($task->status))
                ->map(fn (TaskStatus $s) => $s->value)
                ->join(', ');

            Notification::make()
                ->title('Invalid transition')
                ->body("Cannot move from \"{$task->status->value}\" to \"{$newStatus}\". Allowed: {$allowed}")
                ->danger()
                ->send();

            $this->dispatch('kanban-refresh');

            return;
        }

        $task->update(['status' => $targetStatus]);

        Notification::make()
            ->title('Task moved')
            ->body("\"{$task->title}\" → {$targetStatus->value}")
            ->success()
            ->send();

        unset($this->tasks);
    }

    public function selectTask(string $taskId): void
    {
        $this->selectedTaskId = $taskId;
        $this->dispatch('open-modal', id: 'task-detail');
    }

    public function closeTask(): void
    {
        $this->selectedTaskId = null;
    }

    #[Computed]
    public function selectedTask(): ?Task
    {
        if (! $this->selectedTaskId) {
            return null;
        }

        return Task::with([
            'assignedAgent', 'project', 'dependencies', 'dependents',
            'attempts.agent', 'artifacts', 'thread.messages.fromAgent', 'thread.messages.fromUser',
        ])->find($this->selectedTaskId);
    }

    public function clearFilters(): void
    {
        $this->filterAgent = null;
        $this->filterPriority = null;
        $this->filterTag = null;
        unset($this->tasks);
    }

    public function updatedFilterAgent(): void
    {
        unset($this->tasks);
    }

    public function updatedFilterPriority(): void
    {
        unset($this->tasks);
    }

    public function updatedFilterTag(): void
    {
        unset($this->tasks);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.kanban-board');
    }
}
