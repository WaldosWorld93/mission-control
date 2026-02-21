<x-filament-panels::page>
    <div class="space-y-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Choose a pre-configured squad template to get started quickly. Each template creates a team of agents with roles, models, and SOUL configurations ready to go.
        </p>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            @foreach ($templates as $template)
                <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden">
                    {{-- Card Header --}}
                    <button
                        wire:click="toggleTemplate({{ $template->id }})"
                        class="w-full text-left p-5 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $template->name }}</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $template->description }}</p>
                            </div>
                            <x-heroicon-o-chevron-down
                                class="ml-3 h-5 w-5 flex-shrink-0 text-gray-400 transition-transform duration-200 {{ $expandedTemplateId === $template->id ? 'rotate-180' : '' }}"
                            />
                        </div>

                        <div class="mt-3 flex items-center gap-4">
                            <span class="inline-flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                <x-heroicon-o-cpu-chip class="h-3.5 w-3.5" />
                                {{ $template->agentTemplates->count() }} agents
                            </span>
                            @if ($template->estimated_daily_cost)
                                <span class="inline-flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                    <x-heroicon-o-currency-dollar class="h-3.5 w-3.5" />
                                    ~${{ number_format($template->estimated_daily_cost, 2) }}/day
                                </span>
                            @endif
                            <span class="inline-flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                <x-heroicon-o-wrench-screwdriver class="h-3.5 w-3.5" />
                                {{ $template->use_case }}
                            </span>
                        </div>
                    </button>

                    {{-- Expanded Detail --}}
                    @if ($expandedTemplateId === $template->id)
                        <div class="border-t border-gray-200 dark:border-gray-700">
                            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($template->agentTemplates as $agent)
                                    <div class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full {{ $agent->is_lead ? 'bg-indigo-100 dark:bg-indigo-900/30' : 'bg-gray-100 dark:bg-gray-700' }}">
                                                <span class="text-xs font-semibold {{ $agent->is_lead ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300' }}">
                                                    {{ collect(explode(' ', $agent->name))->map(fn ($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('') }}
                                                </span>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $agent->name }}</span>
                                                    @if ($agent->is_lead)
                                                        <span class="inline-flex items-center rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">Lead</span>
                                                    @endif
                                                </div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $agent->role }}</p>
                                            </div>
                                            <div class="text-right text-xs text-gray-400 dark:text-gray-500 hidden sm:block">
                                                <p>Work: {{ str_replace('anthropic/', '', $agent->work_model ?? 'default') }}</p>
                                                <p>HB: {{ $agent->heartbeat_interval_seconds ? ($agent->heartbeat_interval_seconds / 60) . 'min' : 'default' }}</p>
                                            </div>
                                        </div>
                                        @if ($agent->description)
                                            <p class="mt-1.5 pl-11 text-xs text-gray-500 dark:text-gray-400">{{ $agent->description }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            {{-- Deploy Button --}}
                            <div class="border-t border-gray-200 dark:border-gray-700 px-5 py-4 bg-gray-50 dark:bg-gray-800/50">
                                <button
                                    wire:click="deploy({{ $template->id }})"
                                    wire:confirm="This will create {{ $template->agentTemplates->count() }} agents and a starter project. Continue?"
                                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition-colors"
                                >
                                    <x-heroicon-o-rocket-launch class="h-4 w-4" />
                                    Deploy {{ $template->name }}
                                </button>
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    Creates all agents with API tokens, a starter project, and assigns everyone automatically.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
