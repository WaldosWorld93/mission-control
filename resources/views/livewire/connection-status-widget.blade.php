<div wire:poll.5s="checkHeartbeat">
    @if ($state === 'waiting')
        {{-- Waiting State --}}
        <div class="rounded-xl bg-white p-5 dark:bg-gray-800" style="border: 1px solid #e2e8f0; border-left: 3px solid #6366f1;">
            <div class="flex items-center gap-3">
                <svg class="h-5 w-5 animate-spin" style="color: #6366f1;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        Waiting for first heartbeat from {{ $agent->name }}...
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Run the test command above or start your agent's cron. This widget updates automatically.
                    </p>
                </div>
            </div>
        </div>

    @elseif ($state === 'connected')
        {{-- Connected State --}}
        <div class="rounded-xl bg-white p-5 dark:bg-gray-800" style="border: 1px solid #e2e8f0; border-left: 3px solid #10b981;">
            <div class="flex items-center gap-3">
                <x-heroicon-o-check-circle class="h-6 w-6 flex-shrink-0" style="color: #10b981;" />
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Connected!</p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        First heartbeat received at {{ $connectedAt }}. Status: <strong>{{ $agentStatus }}</strong>.
                    </p>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-3">
                @if ($isMultiAgent && $nextAgent)
                    <x-filament::button
                        tag="a"
                        :href="url('agents/' . $nextAgent->id . '/setup')"
                        icon="heroicon-o-arrow-right"
                        icon-position="after"
                        color="success"
                        size="sm"
                    >
                        Set up {{ $nextAgent->name }}
                    </x-filament::button>
                    <x-filament::link
                        :href="url('home')"
                        size="sm"
                    >
                        Go to Dashboard
                    </x-filament::link>
                @else
                    <x-filament::button
                        tag="a"
                        :href="url('home')"
                        icon="heroicon-o-arrow-right"
                        icon-position="after"
                        color="success"
                        size="sm"
                    >
                        {{ $isMultiAgent ? 'All agents connected! Go to Dashboard' : 'Go to Dashboard' }}
                    </x-filament::button>
                @endif
                <x-filament::link
                    :href="\App\Filament\Resources\AgentResource::getUrl('view', ['record' => $agent])"
                    size="sm"
                >
                    View agent details
                </x-filament::link>
            </div>
            @if ($isMultiAgent && $unconfiguredAgents->isNotEmpty())
                <p class="mt-3 text-xs" style="color: #64748b;">
                    Remaining: {{ $unconfiguredAgents->pluck('name')->join(', ') }} ({{ $unconfiguredAgents->count() }} of {{ $totalAgents }} agents still need setup)
                </p>
            @endif
        </div>

    @elseif ($state === 'error')
        {{-- Error State --}}
        <div class="rounded-xl bg-white p-5 dark:bg-gray-800" style="border: 1px solid #e2e8f0; border-left: 3px solid #f43f5e;">
            <div class="flex items-center gap-3">
                <x-heroicon-o-x-circle class="h-6 w-6 flex-shrink-0" style="color: #f43f5e;" />
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        Heartbeat received with error status
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        The agent reported status: <strong>{{ $agentStatus }}</strong>. Check your agent's logs for details.
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>
