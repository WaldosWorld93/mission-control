@php
    $columnUnderlines = [
        'blocked' => '#f43f5e',
        'backlog' => '#94a3b8',
        'assigned' => '#4f46e5',
        'in_progress' => '#0ea5e9',
        'in_review' => '#8b5cf6',
        'done' => '#10b981',
    ];
@endphp

<div>
    {{-- Filter Pills --}}
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <select wire:model.live="filterAgent"
                class="rounded-lg border border-gray-300 bg-white py-1.5 text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                style="padding-left: 12px; padding-right: 2.5rem;">
            <option value="">All Agents</option>
            @foreach ($this->agents as $agent)
                <option value="{{ $agent->id }}">{{ $agent->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterPriority"
                class="rounded-lg border border-gray-300 bg-white py-1.5 text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                style="padding-left: 12px; padding-right: 2.5rem;">
            <option value="">All Priorities</option>
            @foreach (\App\Enums\TaskPriority::cases() as $priority)
                <option value="{{ $priority->value }}">{{ $priority->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterTag"
                class="rounded-lg border border-gray-300 bg-white py-1.5 text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                style="padding-left: 12px; padding-right: 2.5rem;">
            <option value="">All Tags</option>
            @foreach ($this->allTags as $tag)
                <option value="{{ $tag }}">{{ $tag }}</option>
            @endforeach
        </select>

        @if ($filterAgent || $filterPriority || $filterTag)
            <button wire:click="clearFilters"
                    class="rounded-lg px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                Clear filters
            </button>
        @endif
    </div>

    {{-- Kanban Columns --}}
    <div class="flex gap-5 pb-4"
         style="min-height: calc(100vh - 250px); overflow-x: auto;"
         x-data="kanbanBoard()"
         x-on:kanban-refresh.window="$wire.$refresh()">
        @foreach (static::columns() as $statusValue => [$label, $color])
            @php
                $columnTasks = $this->tasks->where('status.value', $statusValue);
                $underlineColor = $columnUnderlines[$statusValue];
            @endphp
            <div class="flex w-72 min-w-[18rem] flex-shrink-0 flex-col">
                {{-- Column Header --}}
                <div class="mb-3 px-1">
                    <div class="flex items-center gap-2">
                        <h3 style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8;">
                            {{ $label }}
                        </h3>
                        <span style="font-size: 11px; font-weight: 500; color: #cbd5e1;">
                            {{ $columnTasks->count() }}
                        </span>
                    </div>
                    <div style="width: 48px; height: 2px; margin-top: 8px; border-radius: 1px; background-color: {{ $underlineColor }};"></div>
                </div>

                {{-- Drop Zone --}}
                <div class="kanban-column flex flex-1 flex-col"
                     style="gap: 8px; min-height: 120px; padding: 4px 0; border-radius: 8px; transition: background-color 0.15s;"
                     data-status="{{ $statusValue }}"
                     x-ref="column_{{ $statusValue }}">
                    @foreach ($columnTasks as $task)
                        @include('livewire.partials.kanban-card', ['task' => $task])

                        {{-- Subtasks nested under parent --}}
                        @if (! $filterAgent && ! $filterPriority && ! $filterTag)
                            @foreach ($task->subtasks->sortBy('sort_order') as $subtask)
                                <div style="margin-left: 16px; border-left: 2px solid #e2e8f0; padding-left: 8px;">
                                    @include('livewire.partials.kanban-card', ['task' => $subtask, 'isSubtask' => true])
                                </div>
                            @endforeach
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- Task Detail Slide-over --}}
    @if ($this->selectedTask)
        <div x-data="{ open: true }"
             x-show="open"
             x-on:keydown.escape.window="open = false; $wire.closeTask()"
             class="fixed inset-0 z-50 overflow-hidden"
             x-cloak>
            <div class="absolute inset-0 overflow-hidden">
                {{-- Backdrop --}}
                <div x-show="open"
                     x-transition:enter="transition-opacity ease-linear duration-200"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity ease-linear duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="absolute inset-0 bg-gray-500/50 dark:bg-gray-900/50"
                     @click="open = false; $wire.closeTask()">
                </div>

                {{-- Panel --}}
                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                    <div x-show="open"
                         x-transition:enter="transform transition ease-in-out duration-300"
                         x-transition:enter-start="translate-x-full"
                         x-transition:enter-end="translate-x-0"
                         x-transition:leave="transform transition ease-in-out duration-300"
                         x-transition:leave-start="translate-x-0"
                         x-transition:leave-end="translate-x-full"
                         class="pointer-events-auto w-screen max-w-lg">
                        <div class="flex h-full flex-col overflow-y-auto bg-white shadow-xl dark:bg-gray-800">
                            {{-- Header --}}
                            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                                <div class="flex items-center justify-between">
                                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ $this->selectedTask->title }}
                                    </h2>
                                    <button @click="open = false; $wire.closeTask()"
                                            class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                                        <x-heroicon-o-x-mark class="h-5 w-5" />
                                    </button>
                                </div>
                                <div class="mt-2 flex items-center gap-2">
                                    @php
                                        $statusStyles = match($this->selectedTask->status) {
                                            \App\Enums\TaskStatus::Blocked => 'background-color: #fff1f2; color: #be123c;',
                                            \App\Enums\TaskStatus::Backlog => 'background-color: #f1f5f9; color: #475569;',
                                            \App\Enums\TaskStatus::Assigned => 'background-color: #eef2ff; color: #4338ca;',
                                            \App\Enums\TaskStatus::InProgress => 'background-color: #f0f9ff; color: #0369a1;',
                                            \App\Enums\TaskStatus::InReview => 'background-color: #f5f3ff; color: #6d28d9;',
                                            \App\Enums\TaskStatus::Done => 'background-color: #ecfdf5; color: #047857;',
                                            \App\Enums\TaskStatus::Cancelled => 'background-color: #f1f5f9; color: #475569;',
                                        };
                                        $priorityStyles = match($this->selectedTask->priority) {
                                            \App\Enums\TaskPriority::Critical => 'background-color: #fff1f2; color: #be123c;',
                                            \App\Enums\TaskPriority::High => 'background-color: #fffbeb; color: #b45309;',
                                            \App\Enums\TaskPriority::Medium => 'background-color: #f0f9ff; color: #0369a1;',
                                            \App\Enums\TaskPriority::Low => 'background-color: #f1f5f9; color: #475569;',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" style="{{ $statusStyles }}">
                                        {{ $this->selectedTask->status->value }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" style="{{ $priorityStyles }}">
                                        {{ $this->selectedTask->priority->value }}
                                    </span>
                                </div>
                            </div>

                            {{-- Body --}}
                            <div class="flex-1 overflow-y-auto px-6 py-4">
                                @if ($this->selectedTask->description)
                                    <div class="mb-6">
                                        <h4 class="mb-1" style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8;">Description</h4>
                                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $this->selectedTask->description }}</p>
                                    </div>
                                @endif

                                {{-- Assigned Agent --}}
                                <div class="mb-6">
                                    <h4 class="mb-1" style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8;">Assigned Agent</h4>
                                    @if ($this->selectedTask->assignedAgent)
                                        <div class="flex items-center gap-2">
                                            <x-agent-avatar :agent="$this->selectedTask->assignedAgent" size="sm" />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $this->selectedTask->assignedAgent->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-sm text-gray-400">Unassigned</span>
                                    @endif
                                </div>

                                {{-- Dependencies --}}
                                @if ($this->selectedTask->dependencies->isNotEmpty())
                                    <div class="mb-6">
                                        <h4 class="mb-1" style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8;">Blocked By</h4>
                                        <ul class="space-y-1">
                                            @foreach ($this->selectedTask->dependencies as $dep)
                                                <li class="flex items-center gap-2 text-sm">
                                                    <x-heroicon-o-lock-closed class="h-3.5 w-3.5" style="color: #f43f5e;" />
                                                    <span class="text-gray-700 dark:text-gray-300">{{ $dep->title }}</span>
                                                    <span class="text-xs text-gray-400">({{ $dep->status->value }})</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                @if ($this->selectedTask->dependents->isNotEmpty())
                                    <div class="mb-6">
                                        <h4 class="mb-1" style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8;">Blocking</h4>
                                        <ul class="space-y-1">
                                            @foreach ($this->selectedTask->dependents as $dep)
                                                <li class="flex items-center gap-2 text-sm">
                                                    <x-heroicon-o-link class="h-3.5 w-3.5" style="color: #f59e0b;" />
                                                    <span class="text-gray-700 dark:text-gray-300">{{ $dep->title }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                {{-- Dates --}}
                                <div class="mb-6 grid grid-cols-2 gap-4">
                                    <div>
                                        <h4 style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8;">Created</h4>
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $this->selectedTask->created_at->diffForHumans() }}</span>
                                    </div>
                                    @if ($this->selectedTask->started_at)
                                        <div>
                                            <h4 style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8;">Started</h4>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $this->selectedTask->started_at->diffForHumans() }}</span>
                                        </div>
                                    @endif
                                    @if ($this->selectedTask->completed_at)
                                        <div>
                                            <h4 style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8;">Completed</h4>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $this->selectedTask->completed_at->diffForHumans() }}</span>
                                        </div>
                                    @endif
                                    @if ($this->selectedTask->due_at)
                                        <div>
                                            <h4 style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8;">Due</h4>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $this->selectedTask->due_at->diffForHumans() }}</span>
                                        </div>
                                    @endif
                                </div>

                                {{-- Attempts --}}
                                @if ($this->selectedTask->attempts->isNotEmpty())
                                    <div class="mb-6">
                                        <h4 class="mb-2" style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8;">Attempts</h4>
                                        <div class="space-y-2">
                                            @foreach ($this->selectedTask->attempts as $attempt)
                                                <div class="rounded-lg p-3" style="border: 1px solid #e2e8f0;">
                                                    <div class="flex items-center justify-between text-sm">
                                                        <span class="text-gray-700 dark:text-gray-300">{{ $attempt->agent?->name ?? 'Unknown' }}</span>
                                                        @php
                                                            $attemptStyle = match($attempt->status->value) {
                                                                'completed' => 'background-color: #ecfdf5; color: #047857;',
                                                                'failed' => 'background-color: #fff1f2; color: #be123c;',
                                                                'active' => 'background-color: #f0f9ff; color: #0369a1;',
                                                                default => 'background-color: #f1f5f9; color: #475569;',
                                                            };
                                                        @endphp
                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" style="{{ $attemptStyle }}">
                                                            {{ $attempt->status->value }}
                                                        </span>
                                                    </div>
                                                    @if ($attempt->error_message)
                                                        <p class="mt-1 text-xs" style="color: #e11d48;">{{ $attempt->error_message }}</p>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Artifacts --}}
                                @if ($this->selectedTask->artifacts->isNotEmpty())
                                    <div class="mb-6">
                                        <h4 class="mb-2" style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8;">Artifacts</h4>
                                        <div class="space-y-2">
                                            @foreach ($this->selectedTask->artifacts as $artifact)
                                                <div class="flex items-center gap-3 rounded-lg p-3" style="border: 1px solid #e2e8f0;">
                                                    <x-heroicon-o-document class="h-5 w-5 text-gray-400" />
                                                    <div class="flex-1 min-w-0">
                                                        <p class="truncate text-sm font-medium text-gray-700 dark:text-gray-300">{{ $artifact->display_name ?? $artifact->filename }}</p>
                                                        <p class="text-xs text-gray-400">v{{ $artifact->version }} · {{ $artifact->size_bytes ? number_format($artifact->size_bytes / 1024, 1) . ' KB' : '—' }}</p>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Conversation Thread --}}
                                @if ($this->selectedTask->thread)
                                    <div>
                                        <h4 class="mb-2" style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8;">Conversation</h4>
                                        <livewire:thread-chat :thread="$this->selectedTask->thread" :compact="true" :key="'task-thread-'.$this->selectedTask->thread->id" />
                                    </div>
                                @endif
                            </div>

                            {{-- Footer --}}
                            <div class="border-t border-gray-200 px-6 py-3 dark:border-gray-700">
                                <a href="{{ \App\Filament\Resources\TaskResource::getUrl('view', ['record' => $this->selectedTask]) }}"
                                   class="text-sm font-medium" style="color: #4f46e5;">
                                    Open full details &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@script
<script>
    Alpine.data('kanbanBoard', () => ({
        init() {
            this.$nextTick(() => {
                this.initSortable();
            });
        },

        initSortable() {
            const columns = this.$el.querySelectorAll('.kanban-column');
            columns.forEach(column => {
                if (column._sortable) {
                    column._sortable.destroy();
                }

                column._sortable = new Sortable(column, {
                    group: 'kanban',
                    animation: 200,
                    ghostClass: 'kanban-ghost',
                    dragClass: 'kanban-drag',
                    handle: '.kanban-card',
                    draggable: '.kanban-card',
                    onStart: (evt) => {
                        document.querySelectorAll('.kanban-column').forEach(col => {
                            col.style.outline = '2px dashed #cbd5e1';
                            col.style.outlineOffset = '-2px';
                            col.style.borderRadius = '8px';
                        });
                    },
                    onEnd: (evt) => {
                        document.querySelectorAll('.kanban-column').forEach(col => {
                            col.style.outline = '';
                            col.style.outlineOffset = '';
                        });
                        const taskId = evt.item.dataset.taskId;
                        const newStatus = evt.to.dataset.status;

                        if (taskId && newStatus) {
                            $wire.moveTask(taskId, newStatus);
                        }
                    }
                });
            });
        }
    }));
</script>
@endscript
