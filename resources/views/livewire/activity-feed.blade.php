<div>
    {{-- Filters --}}
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <select wire:model.live="filterProject" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
            <option value="">All Projects</option>
            @foreach ($this->projects as $project)
                <option value="{{ $project->id }}">{{ $project->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterAgent" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
            <option value="">All Agents</option>
            @foreach ($this->agents as $agent)
                <option value="{{ $agent->id }}">{{ $agent->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterEventType" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
            <option value="">All Events</option>
            @foreach (\App\Livewire\ActivityFeed::eventTypes() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>

        @if ($filterProject || $filterAgent || $filterEventType)
            <button wire:click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                Clear filters
            </button>
        @endif
    </div>

    {{-- Timeline --}}
    <div class="relative">
        {{-- Vertical line --}}
        <div class="absolute left-4 top-0 bottom-0 w-px bg-gray-200 dark:bg-gray-700"></div>

        <div class="space-y-1">
            @forelse ($activities as $activity)
                @php
                    $colorMap = [
                        'agent.heartbeat' => 'bg-sky-400',
                        'agent.status_changed' => 'bg-amber-400',
                        'agent.paused' => 'bg-rose-400',
                        'task.status_changed' => 'bg-indigo-400',
                        'task.unblocked' => 'bg-emerald-400',
                        'task.claimed' => 'bg-sky-400',
                        'task.stuck' => 'bg-rose-500',
                        'message.created' => 'bg-indigo-400',
                        'artifact.uploaded' => 'bg-violet-400',
                    ];
                    $iconMap = [
                        'agent.heartbeat' => 'heroicon-o-signal',
                        'agent.status_changed' => 'heroicon-o-arrow-path',
                        'agent.paused' => 'heroicon-o-pause-circle',
                        'task.status_changed' => 'heroicon-o-clipboard-document-check',
                        'task.unblocked' => 'heroicon-o-lock-open',
                        'task.claimed' => 'heroicon-o-hand-raised',
                        'task.stuck' => 'heroicon-o-exclamation-triangle',
                        'message.created' => 'heroicon-o-chat-bubble-left',
                        'artifact.uploaded' => 'heroicon-o-paper-clip',
                    ];
                    $dotColor = $colorMap[$activity->event_type] ?? 'bg-gray-400';
                    $icon = $iconMap[$activity->event_type] ?? 'heroicon-o-bolt';
                    $label = \App\Livewire\ActivityFeed::eventTypes()[$activity->event_type] ?? $activity->event_type;
                @endphp

                <div class="relative flex items-start gap-4 py-3 pl-10">
                    {{-- Dot --}}
                    <div class="absolute left-2.5 top-4 h-3 w-3 rounded-full {{ $dotColor }} ring-2 ring-white dark:ring-gray-900"></div>

                    {{-- Content --}}
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <x-dynamic-component :component="$icon" class="h-4 w-4 text-gray-400" />
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $activity->description }}</span>
                        </div>
                        <div class="mt-0.5 flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs dark:bg-gray-700">{{ $label }}</span>
                            @if ($activity->project)
                                <span>{{ $activity->project->name }}</span>
                            @endif
                            <time datetime="{{ $activity->created_at->toISOString() }}">{{ $activity->created_at->diffForHumans() }}</time>
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-12 text-center">
                    <x-heroicon-o-clock class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No activity yet.</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Pagination --}}
    <div class="mt-6">
        {{ $activities->links() }}
    </div>
</div>
