@php
    $columnAccents = [
        'blocked' => ['border' => 'bg-rose-500', 'pill-bg' => 'bg-rose-100 dark:bg-rose-900/30', 'pill-text' => 'text-rose-600 dark:text-rose-400'],
        'backlog' => ['border' => 'bg-slate-400', 'pill-bg' => 'bg-slate-100 dark:bg-slate-800', 'pill-text' => 'text-slate-600 dark:text-slate-400'],
        'assigned' => ['border' => 'bg-indigo-500', 'pill-bg' => 'bg-indigo-100 dark:bg-indigo-900/30', 'pill-text' => 'text-indigo-600 dark:text-indigo-400'],
        'in_progress' => ['border' => 'bg-sky-500', 'pill-bg' => 'bg-sky-100 dark:bg-sky-900/30', 'pill-text' => 'text-sky-600 dark:text-sky-400'],
        'in_review' => ['border' => 'bg-violet-500', 'pill-bg' => 'bg-violet-100 dark:bg-violet-900/30', 'pill-text' => 'text-violet-600 dark:text-violet-400'],
        'done' => ['border' => 'bg-emerald-500', 'pill-bg' => 'bg-emerald-100 dark:bg-emerald-900/30', 'pill-text' => 'text-emerald-600 dark:text-emerald-400'],
    ];
@endphp

<div>
    {{-- Filter Pills --}}
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <select wire:model.live="filterAgent"
                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
            <option value="">All Agents</option>
            @foreach ($this->agents as $agent)
                <option value="{{ $agent->id }}">{{ $agent->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterPriority"
                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
            <option value="">All Priorities</option>
            @foreach (\App\Enums\TaskPriority::cases() as $priority)
                <option value="{{ $priority->value }}">{{ $priority->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterTag"
                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
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
    <div class="flex gap-5 overflow-x-auto pb-4"
         x-data="kanbanBoard()"
         x-on:kanban-refresh.window="$wire.$refresh()">
        @foreach (static::columns() as $statusValue => [$label, $color])
            @php
                $columnTasks = $this->tasks->where('status.value', $statusValue);
                $accent = $columnAccents[$statusValue];
            @endphp
            <div class="flex w-72 min-w-[18rem] flex-shrink-0 flex-col">
                {{-- Column Header --}}
                <div class="mb-3 px-1">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            {{ $label }}
                        </h3>
                        <span class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full px-1.5 text-xs font-medium {{ $accent['pill-bg'] }} {{ $accent['pill-text'] }}">
                            {{ $columnTasks->count() }}
                        </span>
                    </div>
                    <div class="mt-2 h-[3px] rounded-full {{ $accent['border'] }}"></div>
                </div>

                {{-- Drop Zone --}}
                <div class="kanban-column flex min-h-[200px] flex-1 flex-col gap-2 rounded-lg bg-slate-50/50 p-2 dark:bg-slate-800/30"
                     data-status="{{ $statusValue }}"
                     x-ref="column_{{ $statusValue }}">
                    @forelse ($columnTasks as $task)
                        @include('livewire.partials.kanban-card', ['task' => $task])

                        {{-- Subtasks nested under parent --}}
                        @foreach ($task->subtasks->sortBy('sort_order') as $subtask)
                            @if (! $filterAgent && ! $filterPriority && ! $filterTag)
                                <div class="ml-4 border-l-2 border-slate-200 pl-2 dark:border-slate-700">
                                    @include('livewire.partials.kanban-card', ['task' => $subtask, 'isSubtask' => true])
                                </div>
                            @endif
                        @endforeach
                    @empty
                        <div class="flex flex-1 items-center justify-center rounded-lg border-2 border-dashed border-slate-200 dark:border-slate-700">
                            <span class="text-xs text-slate-300 dark:text-slate-600">No tasks</span>
                        </div>
                    @endforelse
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
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                        {{ match($this->selectedTask->status) {
                                            \App\Enums\TaskStatus::Blocked => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
                                            \App\Enums\TaskStatus::Backlog => 'bg-slate-100 text-slate-700 dark:bg-slate-900/30 dark:text-slate-400',
                                            \App\Enums\TaskStatus::Assigned => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
                                            \App\Enums\TaskStatus::InProgress => 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
                                            \App\Enums\TaskStatus::InReview => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
                                            \App\Enums\TaskStatus::Done => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                                            \App\Enums\TaskStatus::Cancelled => 'bg-slate-100 text-slate-700 dark:bg-slate-900/30 dark:text-slate-400',
                                        } }}">
                                        {{ $this->selectedTask->status->value }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                        {{ match($this->selectedTask->priority) {
                                            \App\Enums\TaskPriority::Critical => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
                                            \App\Enums\TaskPriority::High => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                            \App\Enums\TaskPriority::Medium => 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
                                            \App\Enums\TaskPriority::Low => 'bg-slate-100 text-slate-700 dark:bg-slate-900/30 dark:text-slate-400',
                                        } }}">
                                        {{ $this->selectedTask->priority->value }}
                                    </span>
                                </div>
                            </div>

                            {{-- Body --}}
                            <div class="flex-1 overflow-y-auto px-6 py-4">
                                @if ($this->selectedTask->description)
                                    <div class="mb-6">
                                        <h4 class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Description</h4>
                                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $this->selectedTask->description }}</p>
                                    </div>
                                @endif

                                {{-- Assigned Agent --}}
                                <div class="mb-6">
                                    <h4 class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Assigned Agent</h4>
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
                                        <h4 class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Blocked By</h4>
                                        <ul class="space-y-1">
                                            @foreach ($this->selectedTask->dependencies as $dep)
                                                <li class="flex items-center gap-2 text-sm">
                                                    <x-heroicon-o-lock-closed class="h-3.5 w-3.5 text-rose-500" />
                                                    <span class="text-gray-700 dark:text-gray-300">{{ $dep->title }}</span>
                                                    <span class="text-xs text-gray-400">({{ $dep->status->value }})</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                @if ($this->selectedTask->dependents->isNotEmpty())
                                    <div class="mb-6">
                                        <h4 class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Blocking</h4>
                                        <ul class="space-y-1">
                                            @foreach ($this->selectedTask->dependents as $dep)
                                                <li class="flex items-center gap-2 text-sm">
                                                    <x-heroicon-o-link class="h-3.5 w-3.5 text-amber-500" />
                                                    <span class="text-gray-700 dark:text-gray-300">{{ $dep->title }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                {{-- Dates --}}
                                <div class="mb-6 grid grid-cols-2 gap-4">
                                    <div>
                                        <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Created</h4>
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $this->selectedTask->created_at->diffForHumans() }}</span>
                                    </div>
                                    @if ($this->selectedTask->started_at)
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Started</h4>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $this->selectedTask->started_at->diffForHumans() }}</span>
                                        </div>
                                    @endif
                                    @if ($this->selectedTask->completed_at)
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Completed</h4>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $this->selectedTask->completed_at->diffForHumans() }}</span>
                                        </div>
                                    @endif
                                    @if ($this->selectedTask->due_at)
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Due</h4>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $this->selectedTask->due_at->diffForHumans() }}</span>
                                        </div>
                                    @endif
                                </div>

                                {{-- Attempts --}}
                                @if ($this->selectedTask->attempts->isNotEmpty())
                                    <div class="mb-6">
                                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Attempts</h4>
                                        <div class="space-y-2">
                                            @foreach ($this->selectedTask->attempts as $attempt)
                                                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                                    <div class="flex items-center justify-between text-sm">
                                                        <span class="text-gray-700 dark:text-gray-300">{{ $attempt->agent?->name ?? 'Unknown' }}</span>
                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                                            {{ match($attempt->status->value) {
                                                                'completed' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                                                                'failed' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
                                                                'active' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
                                                                default => 'bg-slate-100 text-slate-700 dark:bg-slate-900/30 dark:text-slate-400',
                                                            } }}">
                                                            {{ $attempt->status->value }}
                                                        </span>
                                                    </div>
                                                    @if ($attempt->error_message)
                                                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $attempt->error_message }}</p>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Artifacts --}}
                                @if ($this->selectedTask->artifacts->isNotEmpty())
                                    <div class="mb-6">
                                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Artifacts</h4>
                                        <div class="space-y-2">
                                            @foreach ($this->selectedTask->artifacts as $artifact)
                                                <div class="flex items-center gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
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
                                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Conversation</h4>
                                        <livewire:thread-chat :thread="$this->selectedTask->thread" :compact="true" :key="'task-thread-'.$this->selectedTask->thread->id" />
                                    </div>
                                @endif
                            </div>

                            {{-- Footer --}}
                            <div class="border-t border-gray-200 px-6 py-3 dark:border-gray-700">
                                <a href="{{ \App\Filament\Resources\TaskResource::getUrl('view', ['record' => $this->selectedTask]) }}"
                                   class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                    Open full details →
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
                    onEnd: (evt) => {
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
