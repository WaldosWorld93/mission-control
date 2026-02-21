@php
    $isSubtask = $isSubtask ?? false;

    $priorityDotColor = match($task->priority?->value) {
        'critical' => 'bg-rose-500',
        'high' => 'bg-amber-500',
        'medium' => 'bg-sky-500',
        'low' => 'bg-slate-300',
        default => null,
    };

    $isBlocked = $task->status->value === 'blocked';
    $hasBlockedDependency = $isBlocked && $task->dependencies->isNotEmpty();
    $isBlocking = ($task->dependents_count ?? $task->dependents->count()) > 0;
@endphp

<div class="kanban-card cursor-grab rounded-lg border border-slate-200 bg-white p-3 shadow-sm transition-all duration-150 hover:-translate-y-px hover:shadow-md active:cursor-grabbing dark:border-slate-700 dark:bg-gray-800"
     data-task-id="{{ $task->id }}"
     wire:click="selectTask('{{ $task->id }}')">

    {{-- Priority dot + Title --}}
    <div class="flex items-start gap-2">
        @if ($priorityDotColor)
            <span class="mt-1.5 inline-block h-1.5 w-1.5 flex-shrink-0 rounded-full {{ $priorityDotColor }}" title="{{ $task->priority->value }}"></span>
        @endif
        <p class="line-clamp-2 text-sm font-medium text-slate-900 dark:text-white">{{ $task->title }}</p>
    </div>

    {{-- Bottom row: avatar left, icons right --}}
    <div class="mt-2.5 flex items-center justify-between">
        <div>
            @if ($task->assignedAgent)
                <x-agent-avatar :agent="$task->assignedAgent" size="xs" />
            @endif
        </div>

        <div class="flex items-center gap-2">
            @if ($isBlocking)
                <x-heroicon-o-link class="h-3.5 w-3.5 text-slate-400" title="Blocking other tasks" />
            @endif

            @if ($isBlocked && ! $hasBlockedDependency)
                <x-heroicon-o-lock-closed class="h-3.5 w-3.5 text-rose-400" title="Blocked" />
            @endif

            @if (! $isSubtask && $task->subtasks->isNotEmpty())
                <span class="flex items-center gap-0.5 text-xs text-slate-400">
                    <x-heroicon-o-queue-list class="h-3 w-3" />
                    {{ $task->subtasks->count() }}
                </span>
            @endif
        </div>
    </div>

    {{-- Blocked by indicator --}}
    @if ($hasBlockedDependency)
        <div class="mt-2 flex items-center gap-1 truncate border-t border-slate-100 pt-2 dark:border-slate-700">
            <x-heroicon-o-lock-closed class="h-3 w-3 flex-shrink-0 text-rose-400" />
            <span class="truncate text-xs text-rose-500">Blocked by: {{ $task->dependencies->first()->title }}</span>
        </div>
    @endif
</div>
