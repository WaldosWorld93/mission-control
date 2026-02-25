<div>
    @if ($total === 0)
        <div class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 py-12 dark:border-gray-700">
            <x-heroicon-o-cpu-chip class="mb-3 h-8 w-8 text-gray-300 dark:text-gray-600" />
            <p class="text-sm text-gray-500 dark:text-gray-400">No agents in your team yet.</p>
            <a href="{{ url('templates') }}" class="mt-2 text-sm font-medium" style="color: #4f46e5;">
                Deploy a template to get started
            </a>
        </div>
    @else
        <div class="space-y-6">
            {{-- Progress --}}
            <div>
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ $connected }} of {{ $total }} agents connected
                    </span>
                    @if ($allConnected)
                        <span class="text-sm font-medium" style="color: #059669;">All connected!</span>
                    @endif
                </div>
                <div class="w-full rounded-full" style="height: 6px; background-color: #e2e8f0;">
                    <div
                        class="rounded-full transition-all duration-700 ease-out"
                        style="height: 6px; background-color: #10b981; width: {{ $total > 0 ? round(($connected / $total) * 100) : 0 }}%;"
                    ></div>
                </div>
            </div>

            {{-- Step 1: Lead Agent --}}
            @if ($leadAgent)
                @php
                    $isConnected = $leadAgent->last_heartbeat_at !== null;
                    $isPaused = $leadAgent->is_paused;
                    $isError = $leadAgent->status?->value === 'error';
                @endphp
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide" style="color: #6366f1;">Step 1: Set up your lead agent</p>
                    <div
                        class="flex items-center gap-4 rounded-lg border-2"
                        style="padding: 16px 32px; {{ $isConnected && !$isError ? 'background-color: #ecfdf5; border-color: #10b981;' : 'background-color: #ffffff; border-color: #6366f1;' }}"
                    >
                        {{-- Status Dot --}}
                        <div class="flex-shrink-0">
                            @if ($isConnected && !$isError)
                                <div class="rounded-full" style="width: 10px; height: 10px; background-color: #10b981;"></div>
                            @elseif ($isError || $isPaused)
                                <div class="rounded-full" style="width: 10px; height: 10px; background-color: #e11d48;"></div>
                            @else
                                <div class="relative flex items-center justify-center" style="width: 10px; height: 10px;">
                                    <div class="absolute inset-0 rounded-full animate-ping" style="background-color: rgba(99, 102, 241, 0.3);"></div>
                                    <div class="relative rounded-full" style="width: 10px; height: 10px; background-color: #6366f1;"></div>
                                </div>
                            @endif
                        </div>

                        {{-- Agent Info --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $leadAgent->name }}</span>
                                <span
                                    class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                                    style="background-color: #e0e7ff; color: #4f46e5;"
                                >Lead</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $leadAgent->role ?? 'No role' }}
                            </p>
                        </div>

                        {{-- Status Text --}}
                        <div class="text-right flex-shrink-0">
                            @if ($isConnected && !$isError)
                                <p class="text-xs font-medium" style="color: #059669;">Connected &#10003;</p>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500">{{ $leadAgent->last_heartbeat_at->diffForHumans() }}</p>
                            @elseif ($isError)
                                <p class="text-xs font-medium" style="color: #e11d48;">Connection error</p>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500">{{ $leadAgent->paused_reason ?? 'Check logs' }}</p>
                            @elseif ($isPaused)
                                <p class="text-xs font-medium" style="color: #e11d48;">Paused</p>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500">{{ $leadAgent->paused_reason ?? 'Manually paused' }}</p>
                            @else
                                <p class="text-xs font-medium" style="color: #d97706;">Waiting for heartbeat...</p>
                            @endif
                        </div>

                        {{-- Setup Link --}}
                        <a href="{{ url("agents/{$leadAgent->id}/setup") }}" class="text-xs font-medium flex-shrink-0" style="color: #4f46e5;">
                            Setup &rarr;
                        </a>
                    </div>
                </div>
            @endif

            {{-- Step 2: Squad Agents (or all agents if no lead) --}}
            @php
                $squadAgents = $leadAgent ? $agents->where('is_lead', false) : $agents;
            @endphp
            @if ($squadAgents->isNotEmpty())
                <div>
                    @if ($leadAgent)
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide" style="color: #6366f1;">Step 2: Set up your squad</p>
                    @endif
                    <div class="space-y-2">
                        @foreach ($squadAgents as $agent)
                            @php
                                $isConnected = $agent->last_heartbeat_at !== null;
                                $isPaused = $agent->is_paused;
                                $isError = $agent->status?->value === 'error';
                                $isLocked = $leadAgent && !$leadConnected;
                            @endphp
                            <div
                                class="flex items-center gap-4 rounded-lg border border-gray-200 dark:border-gray-700"
                                style="padding: 16px 32px; {{ $isConnected && !$isError ? 'background-color: #ecfdf5;' : 'background-color: #ffffff;' }} {{ $isLocked ? 'opacity: 0.6;' : '' }}"
                            >
                                {{-- Status Dot --}}
                                <div class="flex-shrink-0">
                                    @if ($isConnected && !$isError)
                                        <div class="rounded-full" style="width: 10px; height: 10px; background-color: #10b981;"></div>
                                    @elseif ($isError || $isPaused)
                                        <div class="rounded-full" style="width: 10px; height: 10px; background-color: #e11d48;"></div>
                                    @else
                                        <div class="relative flex items-center justify-center" style="width: 10px; height: 10px;">
                                            @if (! $isLocked)
                                                <div class="absolute inset-0 rounded-full animate-ping" style="background-color: rgba(245, 158, 11, 0.3);"></div>
                                            @endif
                                            <div class="relative rounded-full" style="width: 10px; height: 10px; background-color: {{ $isLocked ? '#94a3b8' : '#f59e0b' }};"></div>
                                        </div>
                                    @endif
                                </div>

                                {{-- Agent Info --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $agent->name }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $agent->role ?? 'No role' }}
                                    </p>
                                </div>

                                {{-- Status Text --}}
                                <div class="text-right flex-shrink-0">
                                    @if ($isConnected && !$isError)
                                        <p class="text-xs font-medium" style="color: #059669;">Connected &#10003;</p>
                                        <p class="text-[10px] text-gray-400 dark:text-gray-500">{{ $agent->last_heartbeat_at->diffForHumans() }}</p>
                                    @elseif ($isError)
                                        <p class="text-xs font-medium" style="color: #e11d48;">Connection error</p>
                                        <p class="text-[10px] text-gray-400 dark:text-gray-500">{{ $agent->paused_reason ?? 'Check logs' }}</p>
                                    @elseif ($isPaused)
                                        <p class="text-xs font-medium" style="color: #e11d48;">Paused</p>
                                        <p class="text-[10px] text-gray-400 dark:text-gray-500">{{ $agent->paused_reason ?? 'Manually paused' }}</p>
                                    @else
                                        <p class="text-xs font-medium" style="color: #d97706;">Waiting for heartbeat...</p>
                                    @endif
                                </div>

                                {{-- Setup Link --}}
                                @if ($isLocked)
                                    <span class="text-xs font-medium flex-shrink-0" style="color: #94a3b8;" title="Set up your lead agent first — it's needed to test and coordinate the other agents.">
                                        Setup &rarr;
                                    </span>
                                @else
                                    <a href="{{ url("agents/{$agent->id}/setup") }}" class="text-xs font-medium flex-shrink-0" style="color: #4f46e5;">
                                        Setup &rarr;
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if ($isLocked ?? false)
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Set up your lead agent first — it's needed to test and coordinate the other agents.
                        </p>
                    @endif
                </div>
            @endif

            {{-- Gateway Info --}}
            <div class="rounded-lg p-4" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    <strong>Single Gateway:</strong> If all agents run on one machine, they share a single OpenClaw Gateway process. Each agent gets its own workspace, SOUL.md, and skills directory.
                    <strong>Multiple Gateways:</strong> Agents on different machines each run their own Gateway. The setup process is the same — just repeat it on each machine.
                </p>
            </div>

            {{-- All Connected CTA --}}
            @if ($allConnected)
                <div class="text-center">
                    <x-filament::button
                        tag="a"
                        :href="url('home')"
                        icon="heroicon-o-arrow-right"
                        icon-position="after"
                        color="success"
                        size="lg"
                    >
                        All agents connected — Go to Dashboard
                    </x-filament::button>
                </div>
            @endif
        </div>
    @endif
</div>
