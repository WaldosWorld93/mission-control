@php
    $isSubtask = $isSubtask ?? false;

    $priorityLabel = $task->priority?->value;
    $priorityBadgeStyle = match($task->priority?->value) {
        'critical' => 'background-color: #fff1f2; color: #be123c;',
        'high' => 'background-color: #fffbeb; color: #b45309;',
        'medium' => 'background-color: #f0f9ff; color: #0369a1;',
        'low' => 'background-color: #f1f5f9; color: #475569;',
        default => null,
    };

    $isBlocked = $task->status->value === 'blocked';
    $hasBlockedDependency = $isBlocked && $task->dependencies->isNotEmpty();

    $agentStatusColor = '#94a3b8';
    if ($task->assignedAgent) {
        $agentStatusColor = match(true) {
            $task->assignedAgent->last_heartbeat_at !== null && $task->assignedAgent->status?->value !== 'error' => '#10b981',
            $task->assignedAgent->status?->value === 'error' => '#f43f5e',
            default => '#f59e0b',
        };
    }
@endphp

<div class="kanban-card cursor-grab"
     style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: box-shadow 0.15s, transform 0.15s;"
     data-task-id="{{ $task->id }}"
     wire:click="selectTask('{{ $task->id }}')"
     onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)'; this.style.transform='translateY(-1px)';"
     onmouseout="this.style.boxShadow='0 1px 2px rgba(0,0,0,0.04)'; this.style.transform='translateY(0)';">

    {{-- Title --}}
    <p class="line-clamp-2" style="font-size: 13px; font-weight: 600; color: #1e293b; line-height: 1.4; margin: 0;">{{ $task->title }}</p>

    {{-- Bottom row: agent left, badge right --}}
    <div class="flex items-center justify-between" style="margin-top: 10px;">
        <div class="flex items-center" style="gap: 6px;">
            @if ($task->assignedAgent)
                <div style="width: 7px; height: 7px; border-radius: 50%; background-color: {{ $agentStatusColor }}; flex-shrink: 0;"></div>
                <span style="font-size: 12px; color: #94a3b8; font-weight: 500;">{{ $task->assignedAgent->name }}</span>
            @else
                <div style="width: 7px; height: 7px; border-radius: 50%; background-color: #cbd5e1; flex-shrink: 0;"></div>
                <span style="font-size: 12px; color: #cbd5e1; font-weight: 500;">Unassigned</span>
            @endif
        </div>

        <div class="flex items-center" style="gap: 6px;">
            @if ($isBlocked)
                <span style="font-size: 11px; font-weight: 600; padding: 2px 7px; border-radius: 4px; background-color: #fff1f2; color: #be123c;">blocked</span>
            @elseif ($priorityBadgeStyle)
                <span style="font-size: 11px; font-weight: 600; padding: 2px 7px; border-radius: 4px; {{ $priorityBadgeStyle }}">{{ $priorityLabel }}</span>
            @endif

            @if (! $isSubtask && $task->subtasks->isNotEmpty())
                <span class="flex items-center" style="gap: 2px; font-size: 11px; color: #94a3b8;">
                    <x-heroicon-o-queue-list class="h-3 w-3" />
                    {{ $task->subtasks->count() }}
                </span>
            @endif
        </div>
    </div>

    {{-- Blocked by indicator --}}
    @if ($hasBlockedDependency)
        <div class="flex items-center truncate" style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #f1f5f9; gap: 4px;">
            <x-heroicon-o-lock-closed class="h-3 w-3 flex-shrink-0" style="color: #f43f5e;" />
            <span class="truncate" style="font-size: 11px; color: #f43f5e;">Blocked by: {{ $task->dependencies->first()->title }}</span>
        </div>
    @endif
</div>
