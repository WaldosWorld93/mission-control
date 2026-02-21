@php
    $isSubtask = $isSubtask ?? false;

    $priorityDot = match($task->priority->value) {
        'critical' => 'bg-rose-500',
        'high' => 'bg-amber-500',
        'medium' => 'bg-sky-500',
        'low' => 'bg-slate-300',
        default => 'bg-slate-300',
    };
@endphp

<div class="kanban-card cursor-grab rounded-lg border border-gray-200 bg-white p-3 shadow-sm transition hover:shadow-md active:cursor-grabbing dark:border-gray-700 dark:bg-gray-800"
     data-task-id="{{ $task->id }}"
     wire:click="selectTask('{{ $task->id }}')">
    {{-- Title --}}
    <p class="line-clamp-2 text-sm font-medium text-gray-900 dark:text-white">
        {{ $task->title }}
    </p>

    {{-- Blocked indicator --}}
    @if ($task->status->value === 'blocked' && $task->dependencies->isNotEmpty())
        <div class="mt-1.5 flex items-center gap-1 text-xs text-rose-500">
            <x-heroicon-o-lock-closed class="h-3 w-3" />
            <span>Blocked by: {{ $task->dependencies->first()->title }}</span>
        </div>
    @endif

    {{-- Bottom row --}}
    <div class="mt-2 flex items-center justify-between">
        <div class="flex items-center gap-2">
            {{-- Agent avatar --}}
            @if ($task->assignedAgent)
                <x-agent-avatar :agent="$task->assignedAgent" size="sm" />
            @endif

            {{-- Priority dot --}}
            <span class="inline-block h-2 w-2 rounded-full {{ $priorityDot }}" title="{{ $task->priority->value }}"></span>
        </div>

        <div class="flex items-center gap-1.5">
            {{-- Dependency icon --}}
            @if ($task->dependents_count ?? $task->dependents->count() > 0)
                <x-heroicon-o-link class="h-3.5 w-3.5 text-slate-400" title="Has dependents" />
            @endif

            {{-- Subtask indicator --}}
            @if (! $isSubtask && $task->subtasks->isNotEmpty())
                <span class="text-xs text-slate-400">{{ $task->subtasks->count() }} sub</span>
            @endif
        </div>
    </div>
</div>
