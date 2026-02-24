<x-filament-panels::page>
    <div style="max-width: 64rem; margin: 0 auto; padding-left: 2rem; padding-right: 2rem;">
    {{-- Quick Stats --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-900/20">
                    <x-heroicon-o-check-circle class="h-5 w-5 text-emerald-500" />
                </div>
                <div>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['tasks_completed_today'] }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Completed today</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-900/20">
                    <x-heroicon-o-folder class="h-5 w-5 text-indigo-500" />
                </div>
                <div>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['active_projects'] }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Active projects</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-50 dark:bg-sky-900/20">
                    <x-heroicon-o-cpu-chip class="h-5 w-5 text-sky-500" />
                </div>
                <div>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ $stats['agents_online'] }}<span class="text-sm font-normal text-slate-400">/{{ $stats['agents_total'] }}</span>
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Agents online</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-900/20">
                    <x-heroicon-o-clipboard-document-list class="h-5 w-5 text-amber-500" />
                </div>
                <div>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['open_tasks'] }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Open tasks</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Alerts Section --}}
    @if ($alerts['paused']->isNotEmpty() || $alerts['stuck']->isNotEmpty() || $alerts['offline']->isNotEmpty())
        <div class="mt-6 space-y-2">
            @foreach ($alerts['paused'] as $agent)
                <div class="flex items-center gap-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 dark:border-rose-800 dark:bg-rose-950/20">
                    <x-heroicon-o-pause-circle class="h-5 w-5 flex-shrink-0 text-rose-500" />
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-rose-800 dark:text-rose-300">{{ $agent->name }} is paused</p>
                        @if ($agent->paused_reason)
                            <p class="truncate text-xs text-rose-600 dark:text-rose-400">{{ $agent->paused_reason }}</p>
                        @endif
                    </div>
                    <a href="{{ \App\Filament\Resources\AgentResource::getUrl('edit', ['record' => $agent]) }}"
                       class="text-xs font-medium text-rose-600 hover:text-rose-500 dark:text-rose-400">Un-pause</a>
                </div>
            @endforeach

            @foreach ($alerts['stuck'] as $task)
                <div class="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-950/20">
                    <x-heroicon-o-exclamation-triangle class="h-5 w-5 flex-shrink-0 text-amber-500" />
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-amber-800 dark:text-amber-300">Task may be stuck: {{ $task->title }}</p>
                        <p class="truncate text-xs text-amber-600 dark:text-amber-400">
                            Assigned to {{ $task->assignedAgent?->name ?? 'unknown' }}
                            · {{ $task->project?->name }}
                        </p>
                    </div>
                    <a href="{{ \App\Filament\Resources\TaskResource::getUrl('view', ['record' => $task]) }}"
                       class="text-xs font-medium text-amber-600 hover:text-amber-500 dark:text-amber-400">View</a>
                </div>
            @endforeach

            @foreach ($alerts['offline'] as $agent)
                <div class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50">
                    <x-heroicon-o-signal-slash class="h-5 w-5 flex-shrink-0 text-slate-400" />
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $agent->name }} is offline</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Last seen {{ $agent->last_heartbeat_at?->diffForHumans() ?? 'never' }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Main Content: Agent Grid + Activity Feed --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Agent Grid (2/3 width) --}}
        <div class="lg:col-span-2">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Agents</h3>

            @if ($agents->isEmpty())
                <div class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-200 py-12 dark:border-slate-700">
                    <x-heroicon-o-cpu-chip class="mb-3 h-8 w-8 text-slate-300 dark:text-slate-600" />
                    <p class="text-sm text-slate-400 dark:text-slate-500">No agents yet.</p>
                    <a href="{{ \App\Filament\Resources\AgentResource::getUrl('create') }}"
                       class="mt-2 text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Create your first agent</a>
                </div>
            @else
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach ($agents as $agent)
                        <a href="{{ \App\Filament\Resources\AgentResource::getUrl('view', ['record' => $agent]) }}"
                           class="group rounded-xl border bg-white p-4 shadow-sm transition-all duration-150 hover:-translate-y-px hover:shadow-md dark:bg-gray-800
                               {{ $agent->is_paused ? 'border-rose-200 bg-rose-50/50 dark:border-rose-800 dark:bg-rose-950/10' : 'border-gray-200 dark:border-gray-700' }}">
                            <div class="flex items-start gap-3">
                                <x-agent-avatar :agent="$agent" size="md" />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ $agent->name }}</span>
                                        <x-status-dot :status="$agent->status" size="sm" />
                                        @if ($agent->is_paused)
                                            <span class="inline-flex items-center rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-medium text-rose-700 dark:bg-rose-900/30 dark:text-rose-400">Paused</span>
                                        @endif
                                    </div>
                                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $agent->role ?? 'No role' }}</p>
                                </div>
                            </div>

                            <div class="mt-3 space-y-1">
                                @php
                                    $currentTask = $agent->assignedTasks->first();
                                @endphp
                                @if ($currentTask)
                                    <p class="truncate text-xs text-slate-700 dark:text-slate-300">
                                        <span class="text-slate-400">Working on:</span> {{ $currentTask->title }}
                                    </p>
                                @else
                                    <p class="text-xs text-slate-400 dark:text-slate-500">Idle</p>
                                @endif

                                <p class="text-xs text-slate-400 dark:text-slate-500">
                                    Last heartbeat: {{ $agent->last_heartbeat_at?->diffForHumans() ?? 'Never' }}
                                </p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Activity Feed (1/3 width) --}}
        <div>
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Recent Activity</h3>
                <a href="{{ url('activity') }}" class="text-xs text-indigo-500 hover:text-indigo-700 dark:text-indigo-400">View all</a>
            </div>

            @if ($activityFeed->isEmpty())
                <div class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-200 py-12 dark:border-slate-700">
                    <x-heroicon-o-clock class="mb-3 h-8 w-8 text-slate-300 dark:text-slate-600" />
                    <p class="text-sm text-slate-400 dark:text-slate-500">No activity yet.</p>
                </div>
            @else
                <div class="relative space-y-0">
                    {{-- Timeline line --}}
                    <div class="absolute left-3 top-2 bottom-2 w-px bg-slate-200 dark:bg-slate-700"></div>

                    @foreach ($activityFeed as $event)
                        <div class="relative flex gap-3 py-2">
                            {{-- Timeline dot --}}
                            <div class="relative z-10 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-{{ $event['color'] }}-100 dark:bg-{{ $event['color'] }}-900/30">
                                <div class="h-2 w-2 rounded-full bg-{{ $event['color'] }}-500"></div>
                            </div>

                            <div class="flex-1 min-w-0">
                                <p class="truncate text-xs font-medium text-slate-700 dark:text-slate-300">{{ $event['title'] }}</p>
                                <p class="truncate text-xs text-slate-400 dark:text-slate-500">{{ $event['detail'] }}</p>
                                <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ $event['at']->diffForHumans() }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    </div>
</x-filament-panels::page>
