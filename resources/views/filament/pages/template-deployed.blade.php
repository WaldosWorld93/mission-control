<x-filament-panels::page>
    <div style="max-width: 64rem; margin: 0 auto; padding-left: 2rem; padding-right: 2rem;">
    @if (empty($tokens))
        <div class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 py-12 dark:border-gray-700">
            <x-heroicon-o-check-circle class="mb-3 h-8 w-8 text-gray-300 dark:text-gray-600" />
            <p class="text-sm text-gray-500 dark:text-gray-400">No deployment in progress.</p>
            <a href="{{ url('templates') }}" class="mt-2 text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                Browse templates
            </a>
        </div>
    @else
        <div class="space-y-6">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-800 dark:bg-emerald-950/20">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-check-circle class="h-6 w-6 text-emerald-500" />
                    <div>
                        <h3 class="text-base font-semibold text-emerald-800 dark:text-emerald-300">{{ $templateName }} deployed!</h3>
                        <p class="text-sm text-emerald-600 dark:text-emerald-400">Your agents and project have been created. Copy the API tokens below — they won't be shown again.</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/20">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-amber-500" />
                    <p class="text-sm font-medium text-amber-800 dark:text-amber-300">Save these tokens now. They cannot be recovered after you leave this page.</p>
                </div>
            </div>

            <div class="space-y-3">
                @foreach ($tokens as $entry)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $entry['name'] }}</span>
                            </div>
                            <button
                                x-data="{ copied: false }"
                                x-on:click="
                                    navigator.clipboard.writeText('{{ $entry['token'] }}');
                                    copied = true;
                                    setTimeout(() => copied = false, 2000);
                                "
                                class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 transition-colors"
                            >
                                <template x-if="!copied">
                                    <span class="flex items-center gap-1.5">
                                        <x-heroicon-o-clipboard class="h-3.5 w-3.5" />
                                        Copy
                                    </span>
                                </template>
                                <template x-if="copied">
                                    <span class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                                        <x-heroicon-o-check class="h-3.5 w-3.5" />
                                        Copied
                                    </span>
                                </template>
                            </button>
                        </div>
                        <code class="mt-2 block rounded bg-gray-100 px-3 py-2 font-mono text-xs text-gray-700 dark:bg-gray-900 dark:text-gray-300 select-all">{{ $entry['token'] }}</code>
                    </div>
                @endforeach
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ url('setup/squad') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition-colors">
                    Continue to Setup
                    <x-heroicon-o-arrow-right class="h-4 w-4" />
                </a>
                <a href="{{ url('home') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    Skip to dashboard
                </a>
            </div>
        </div>
    @endif
    </div>
</x-filament-panels::page>
