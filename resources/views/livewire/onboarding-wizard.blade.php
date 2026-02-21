<div class="mx-auto max-w-2xl">
    @if ($step === 'welcome')
        <div class="text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-100 dark:bg-indigo-900/30">
                <x-heroicon-o-rocket-launch class="h-8 w-8 text-indigo-500" />
            </div>
            <h2 class="mt-4 text-xl font-semibold text-gray-900 dark:text-white">Welcome to Mission Control</h2>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Let's get your AI agent squad set up. Choose how you'd like to start.
            </p>
        </div>

        <div class="mt-8 space-y-3">
            {{-- Option 1: Start from a template --}}
            <button
                wire:click="chooseTemplate"
                class="group w-full rounded-xl border border-gray-200 bg-white p-5 text-left transition-all hover:border-indigo-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-indigo-600"
            >
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-900/20">
                        <x-heroicon-o-square-3-stack-3d class="h-5 w-5 text-indigo-500" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Start from a template</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Choose a pre-built squad — content marketing, product development, research, or customer support. We'll create all agents and a project for you.
                        </p>
                    </div>
                    <x-heroicon-o-chevron-right class="mt-0.5 h-5 w-5 flex-shrink-0 text-gray-300 group-hover:text-indigo-500 dark:text-gray-600" />
                </div>
            </button>

            {{-- Option 2: Set up manually --}}
            <button
                wire:click="chooseManual"
                class="group w-full rounded-xl border border-gray-200 bg-white p-5 text-left transition-all hover:border-indigo-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-indigo-600"
            >
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-900/20">
                        <x-heroicon-o-wrench-screwdriver class="h-5 w-5 text-emerald-500" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Set up agents manually</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Create agents one by one with custom roles, models, and configurations. Full control over your setup.
                        </p>
                    </div>
                    <x-heroicon-o-chevron-right class="mt-0.5 h-5 w-5 flex-shrink-0 text-gray-300 group-hover:text-indigo-500 dark:text-gray-600" />
                </div>
            </button>

            {{-- Option 3: I already have OpenClaw agents --}}
            <button
                wire:click="chooseExisting"
                class="group w-full rounded-xl border border-gray-200 bg-white p-5 text-left transition-all hover:border-indigo-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-indigo-600"
            >
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-sky-50 dark:bg-sky-900/20">
                        <x-heroicon-o-link class="h-5 w-5 text-sky-500" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">I already have OpenClaw agents</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Connect your existing agents to Mission Control. You'll create agent records and get API tokens to configure in your OpenClaw setup.
                        </p>
                    </div>
                    <x-heroicon-o-chevron-right class="mt-0.5 h-5 w-5 flex-shrink-0 text-gray-300 group-hover:text-indigo-500 dark:text-gray-600" />
                </div>
            </button>
        </div>

        {{-- Skip link --}}
        <div class="mt-6 text-center">
            <button wire:click="skip" class="text-sm text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                I'll set up later
            </button>
        </div>

    @elseif ($step === 'existing')
        <div class="text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-sky-100 dark:bg-sky-900/30">
                <x-heroicon-o-link class="h-8 w-8 text-sky-500" />
            </div>
            <h2 class="mt-4 text-xl font-semibold text-gray-900 dark:text-white">Connect Existing Agents</h2>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Here's how to connect your OpenClaw agents to Mission Control:
            </p>
        </div>

        <div class="mt-6 space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                <ol class="space-y-4 text-sm text-gray-600 dark:text-gray-300">
                    <li class="flex gap-3">
                        <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400">1</span>
                        <span>Create an agent in Mission Control for each OpenClaw agent you want to connect. You'll get an API token for each one.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400">2</span>
                        <span>Add the Mission Control heartbeat skill to each agent's SKILL.md configuration.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400">3</span>
                        <span>Configure the agent's cron job to call the heartbeat endpoint with its API token.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400">4</span>
                        <span>Once connected, your agents will appear on the dashboard and you can coordinate work through Mission Control.</span>
                    </li>
                </ol>
            </div>

            <div class="flex items-center justify-between">
                <button wire:click="$set('step', 'welcome')" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    &larr; Back
                </button>
                <button
                    wire:click="finishExisting"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition-colors"
                >
                    Create your first agent
                    <x-heroicon-o-arrow-right class="h-4 w-4" />
                </button>
            </div>
        </div>

        {{-- Skip link --}}
        <div class="mt-6 text-center">
            <button wire:click="skip" class="text-sm text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                I'll set up later
            </button>
        </div>
    @endif
</div>
