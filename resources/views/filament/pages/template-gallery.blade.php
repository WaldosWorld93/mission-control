<x-filament-panels::page>
    <div style="max-width: 64rem; margin: 0 auto; padding-left: 2rem; padding-right: 2rem;">
    <div class="space-y-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Choose a pre-configured squad template to get started quickly. Each template creates a team of agents with roles, models, and SOUL configurations ready to go.
        </p>

        <div class="space-y-4">
            @foreach ($templates as $template)
                @php
                    $agentCount = $template->agentTemplates->count();
                @endphp
                <div
                    class="group rounded-xl border border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="p-6">
                        {{-- Header: title + deploy button --}}
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $template->name }}</h3>
                                <p class="mt-1 line-clamp-2 text-sm text-slate-500 dark:text-gray-400">{{ $template->description }}</p>
                            </div>
                            <x-filament::button
                                wire:click="deploy({{ $template->id }})"
                                wire:confirm="This will create {{ $agentCount }} agents and a starter project. Continue?"
                                icon="heroicon-o-arrow-right"
                                icon-position="after"
                            >
                                Deploy
                            </x-filament::button>
                        </div>

                        {{-- Stats row --}}
                        <div class="mt-4 flex flex-wrap items-center gap-6">
                            <span class="inline-flex items-center gap-1.5 text-sm text-slate-600 dark:text-gray-400">
                                <x-heroicon-o-users class="h-4 w-4 text-slate-400 dark:text-gray-500" />
                                {{ $agentCount }} agents
                            </span>
                            @if ($template->estimated_daily_cost)
                                <span class="inline-flex items-center gap-1.5 text-sm text-slate-600 dark:text-gray-400">
                                    <x-heroicon-o-currency-dollar class="h-4 w-4 text-slate-400 dark:text-gray-500" />
                                    ~${{ number_format($template->estimated_daily_cost, 2) }}/day
                                </span>
                            @endif
                            <span class="inline-flex items-center gap-1.5 text-sm text-slate-600 dark:text-gray-400">
                                <x-heroicon-o-tag class="h-4 w-4 text-slate-400 dark:text-gray-500" />
                                {{ $template->use_case }}
                            </span>
                        </div>

                        {{-- Agent preview row + show details toggle --}}
                        <div class="mt-5 flex flex-wrap items-end justify-between gap-4">
                            <div class="flex flex-wrap items-center gap-4">
                                @foreach ($template->agentTemplates as $agentTpl)
                                    @php
                                        $words = explode(' ', trim($agentTpl->name));
                                        $initials = count($words) >= 2
                                            ? mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1))
                                            : mb_strtoupper(mb_substr($agentTpl->name, 0, 2));
                                        $palette = [
                                            '#818cf8', '#34d399', '#fbbf24', '#fb7185',
                                            '#38bdf8', '#a78bfa', '#2dd4bf', '#f472b6',
                                            '#22d3ee', '#fb923c', '#a3e635', '#e879f9',
                                        ];
                                        $avatarColor = $palette[crc32($agentTpl->name) % count($palette)];
                                        $shortRole = Str::limit($agentTpl->role, 8, '');
                                    @endphp
                                    <div class="flex flex-col items-center gap-1">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold text-white"
                                             style="background-color: {{ $avatarColor }}"
                                             title="{{ $agentTpl->name }}">
                                            {{ $initials }}
                                        </div>
                                        <span class="text-xs text-slate-400 dark:text-gray-500">{{ $shortRole }}</span>
                                    </div>
                                @endforeach
                            </div>

                            <x-filament::link
                                tag="button"
                                wire:click="toggleTemplate({{ $template->id }})"
                                icon="{{ $expandedTemplateId === $template->id ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down' }}"
                                icon-position="after"
                            >
                                {{ $expandedTemplateId === $template->id ? 'Hide details' : 'Show details' }}
                            </x-filament::link>
                        </div>

                        {{-- Expanded agent details --}}
                        @if ($expandedTemplateId === $template->id)
                            <div class="mt-4 overflow-x-auto rounded-lg bg-slate-50 p-4 dark:bg-gray-900/50">
                                <table class="w-full min-w-[500px]">
                                    <thead>
                                        <tr class="text-left text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-gray-500">
                                            <th class="pb-3 pr-4">Agent</th>
                                            <th class="pb-3 pr-4">Role</th>
                                            <th class="pb-3 pr-4">Work Model</th>
                                            <th class="pb-3">Heartbeat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($template->agentTemplates as $agentTpl)
                                            @php
                                                $words = explode(' ', trim($agentTpl->name));
                                                $initials = count($words) >= 2
                                                    ? mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1))
                                                    : mb_strtoupper(mb_substr($agentTpl->name, 0, 2));
                                                $palette = [
                                                    '#818cf8', '#34d399', '#fbbf24', '#fb7185',
                                                    '#38bdf8', '#a78bfa', '#2dd4bf', '#f472b6',
                                                    '#22d3ee', '#fb923c', '#a3e635', '#e879f9',
                                                ];
                                                $avatarColor = $palette[crc32($agentTpl->name) % count($palette)];
                                                $hbMinutes = $agentTpl->heartbeat_interval_seconds
                                                    ? intval($agentTpl->heartbeat_interval_seconds / 60) . 'min'
                                                    : 'default';
                                                $workModel = str_replace('anthropic/', '', $agentTpl->work_model ?? 'default');
                                            @endphp
                                            <tr class="border-b border-slate-100 last:border-0 dark:border-gray-700/50">
                                                <td class="py-3 pr-4 align-top">
                                                    <div class="flex items-center gap-2.5">
                                                        <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold text-white"
                                                             style="background-color: {{ $avatarColor }}">
                                                            {{ $initials }}
                                                        </div>
                                                        <span class="font-medium text-slate-900 dark:text-white">{{ $agentTpl->name }}</span>
                                                        @if ($agentTpl->is_lead)
                                                            <x-filament::badge size="sm">
                                                                Lead
                                                            </x-filament::badge>
                                                        @endif
                                                    </div>
                                                    @if ($agentTpl->description)
                                                        <p class="mt-1 pl-[34px] text-xs text-slate-400 dark:text-gray-500">{{ $agentTpl->description }}</p>
                                                    @endif
                                                </td>
                                                <td class="py-3 pr-4 align-top text-sm text-slate-500 dark:text-gray-400">{{ $agentTpl->role }}</td>
                                                <td class="py-3 pr-4 align-top font-mono text-xs text-slate-600 dark:text-gray-400">{{ $workModel }}</td>
                                                <td class="py-3 align-top text-xs text-slate-400 dark:text-gray-500">{{ $hbMinutes }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    </div>
</x-filament-panels::page>
