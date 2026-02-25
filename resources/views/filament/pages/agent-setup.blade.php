<x-filament-panels::page>
    <div style="max-width: 64rem; margin: 0 auto; padding-left: 2rem; padding-right: 2rem;">
    <div class="mx-auto max-w-3xl space-y-10">

        {{-- Success Banner --}}
        @if (session('agent_created'))
            <div class="flex items-center gap-3 rounded-xl p-4" style="background-color: #ecfdf5; border: 1px solid #a7f3d0;">
                <x-heroicon-o-check-circle class="h-6 w-6 flex-shrink-0" style="color: #059669;" />
                <p class="text-sm font-medium" style="color: #065f46;">
                    Agent created! Follow the steps below to connect it.
                </p>
            </div>
        @endif

        {{-- Header --}}
        <div>
            <div class="flex items-center gap-3">
                <x-agent-avatar :agent="$agent" size="lg" />
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $agent->name }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $agent->role ?? 'Agent' }}</p>
                </div>
            </div>
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                Follow these steps to connect <strong>{{ $agent->name }}</strong> to Mission Control. Once connected, the agent will check in automatically on every heartbeat.
            </p>

            {{-- Squad Progress Bar --}}
            <livewire:squad-progress-bar :current-agent="$agent" />
        </div>

        {{-- Step 1: Prerequisites --}}
        <section>
            <div class="flex items-center gap-3 mb-4">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold"
                    style="background-color: #e0e7ff; color: #4f46e5;"
                >1</div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Prerequisites</h3>
            </div>
            <div class="gap-3 rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800" style="margin-left: 44px; padding: 20px 32px;">
                <ul class="space-y-2">
                    <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <x-heroicon-o-check-circle class="h-5 w-5 flex-shrink-0" style="color: #10b981;" />
                        OpenClaw installed and running
                    </li>
                    <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <x-heroicon-o-check-circle class="h-5 w-5 flex-shrink-0" style="color: #10b981;" />
                        Access to your <code class="rounded bg-stone-100 px-1.5 py-0.5 text-xs dark:bg-gray-900">openclaw.json</code> configuration file
                    </li>
                    <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <x-heroicon-o-check-circle class="h-5 w-5 flex-shrink-0" style="color: #10b981;" />
                        Terminal access to create workspace files
                    </li>
                </ul>
            </div>
        </section>

        {{-- Step 2: Add Agent to OpenClaw Gateway --}}
        <section>
            <div class="flex items-center gap-3 mb-4">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold"
                    style="background-color: #e0e7ff; color: #4f46e5;"
                >2</div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Add Agent to OpenClaw Gateway</h3>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden" style="margin-left: 44px;">
                {{-- Tabs --}}
                <div class="flex border-b border-gray-200 dark:border-gray-700">
                    <button
                        wire:click="setSkillTab('ask')"
                        class="flex-1 px-4 py-3 text-sm font-medium transition-colors text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                        @if ($skillTab === 'ask') style="color: #4f46e5; border-bottom: 2px solid #4f46e5;" @endif
                    >
                        Option A: Ask Your Agent
                    </button>
                    <button
                        wire:click="setSkillTab('manual')"
                        class="flex-1 px-4 py-3 text-sm font-medium transition-colors text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                        @if ($skillTab === 'manual') style="color: #4f46e5; border-bottom: 2px solid #4f46e5;" @endif
                    >
                        Option B: Manual Setup
                    </button>
                </div>

                <div style="padding: 20px 32px;">
                    @if ($skillTab === 'ask')
                        <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                            Paste this into a chat with your {{ $leadAgent ? 'main agent (' : '' }}<strong>{{ $leadAgent ? $leadAgent->name : $agent->name }}</strong>{{ $leadAgent ? ')' : ' agent' }}:
                        </p>
                        <x-code-block>Add a new agent entry to the openclaw.json configuration at ~/.openclaw/openclaw.json.

Add this entry to the "agents" array (create the array if it doesn't exist):

{{ $openclawAgentConfig }}

The "tools" object controls which tools the agent has access to. The "profile" sets the base set of tools, and "allow"/"deny" can override specific tool groups.

Don't create a new file — add this to the existing openclaw.json. If there are already other agents in the array, add this one alongside them.</x-code-block>
                    @else
                        <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                            Add this agent configuration to the <code class="rounded bg-stone-100 px-1.5 py-0.5 text-xs dark:bg-gray-900">agents</code> array in your <code class="rounded bg-stone-100 px-1.5 py-0.5 text-xs dark:bg-gray-900">openclaw.json</code>:
                        </p>

                        <x-code-block language="json">{{ $openclawAgentConfig }}</x-code-block>

                        <div class="mt-4 rounded-lg p-3" style="background-color: #f0f9ff; border: 1px solid #bae6fd;">
                            <ul class="space-y-1 text-xs" style="color: #0369a1;">
                                <li><strong>name:</strong> Uses the slug format (<code class="text-xs">{{ $agentSlug }}</code>) — must match across config and workspace.</li>
                                <li><strong>workspace:</strong> Points to <code class="text-xs">{{ $workspacePath }}</code> — we'll create this directory in Step 3.</li>
                                <li>If you already have agents configured, add this entry to the existing <code class="text-xs">agents</code> array.</li>
                            </ul>
                        </div>

                        {{-- Collapsible: Full config --}}
                        <div class="mt-4 rounded-lg border border-gray-200 dark:border-gray-700">
                            <button
                                wire:click="toggleFile('fullConfig')"
                                class="flex w-full items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                            >
                                <span>View full openclaw.json with all squad agents</span>
                                <x-heroicon-o-chevron-down class="h-4 w-4 transition-transform {{ $expandedFile === 'fullConfig' ? 'rotate-180' : '' }}" />
                            </button>
                            @if ($expandedFile === 'fullConfig')
                                <div class="border-t border-gray-200 dark:border-gray-700 p-4">
                                    <x-code-block language="json" maxHeight="300px">{{ $openclawFullConfig }}</x-code-block>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- Step 3: Configure Agent Workspace Files --}}
        <section>
            <div class="flex items-center gap-3 mb-4">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold"
                    style="background-color: #e0e7ff; color: #4f46e5;"
                >3</div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Configure Agent Workspace Files</h3>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden" style="margin-left: 44px;">
                {{-- Tabs --}}
                <div class="flex border-b border-gray-200 dark:border-gray-700">
                    <button
                        wire:click="setSkillTab('ask')"
                        class="flex-1 px-4 py-3 text-sm font-medium transition-colors text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                        @if ($skillTab === 'ask') style="color: #4f46e5; border-bottom: 2px solid #4f46e5;" @endif
                    >
                        Option A: Ask Your Agent
                    </button>
                    <button
                        wire:click="setSkillTab('manual')"
                        class="flex-1 px-4 py-3 text-sm font-medium transition-colors text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                        @if ($skillTab === 'manual') style="color: #4f46e5; border-bottom: 2px solid #4f46e5;" @endif
                    >
                        Option B: Manual Setup
                    </button>
                </div>

                <div style="padding: 20px 32px;">
                    @if ($skillTab === 'ask')
                        <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                            Paste this into a chat with your {{ $leadAgent ? 'main agent (' : '' }}<strong>{{ $leadAgent ? $leadAgent->name : $agent->name }}</strong>{{ $leadAgent ? ')' : ' agent' }}:
                        </p>
                        <x-code-block>Create the workspace files for the {{ $agent->name }} agent. Run this script:

{{ $createAllFilesScript }}</x-code-block>
                    @else
                        {{-- Copy Setup Script button --}}
                        <div class="mb-4" x-data="{ copied: false }">
                            <button
                                x-on:click="
                                    navigator.clipboard.writeText($refs.setupScript.textContent.trim());
                                    copied = true;
                                    setTimeout(() => copied = false, 2000);
                                "
                                class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                                style="background-color: #e0e7ff; color: #4f46e5; border: 1px solid #c7d2fe;"
                            >
                                <template x-if="!copied">
                                    <span class="flex items-center gap-1.5">
                                        <x-heroicon-o-clipboard class="h-4 w-4" />
                                        Copy Setup Script
                                    </span>
                                </template>
                                <template x-if="copied">
                                    <span class="flex items-center gap-1.5" style="color: #059669;">
                                        <x-heroicon-o-check class="h-4 w-4" />
                                        Copied!
                                    </span>
                                </template>
                            </button>
                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">Creates all files at once</span>
                            <pre class="hidden" x-ref="setupScript">{{ $createAllFilesScript }}</pre>
                        </div>

                        <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                            Create the workspace directory:
                        </p>
                        <x-code-block>mkdir -p {{ $workspacePath }}</x-code-block>

                        <p class="mt-4 mb-3 text-sm text-gray-600 dark:text-gray-300">
                            Then create each workspace file:
                        </p>

                        <div class="space-y-2">
                            {{-- identity.md --}}
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700">
                                <button
                                    wire:click="toggleFile('identity')"
                                    class="flex w-full items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                >
                                    <span><code class="text-xs rounded bg-stone-100 px-1.5 py-0.5 dark:bg-gray-900">{{ $workspacePath }}/identity.md</code></span>
                                    <x-heroicon-o-chevron-down class="h-4 w-4 transition-transform {{ $expandedFile === 'identity' ? 'rotate-180' : '' }}" />
                                </button>
                                @if ($expandedFile === 'identity')
                                    <div class="border-t border-gray-200 dark:border-gray-700 p-4">
                                        <x-code-block language="markdown" maxHeight="300px">{{ $identityMd }}</x-code-block>
                                    </div>
                                @endif
                            </div>

                            {{-- agents.md --}}
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700">
                                <button
                                    wire:click="toggleFile('agents')"
                                    class="flex w-full items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                >
                                    <span><code class="text-xs rounded bg-stone-100 px-1.5 py-0.5 dark:bg-gray-900">{{ $workspacePath }}/agents.md</code></span>
                                    <x-heroicon-o-chevron-down class="h-4 w-4 transition-transform {{ $expandedFile === 'agents' ? 'rotate-180' : '' }}" />
                                </button>
                                @if ($expandedFile === 'agents')
                                    <div class="border-t border-gray-200 dark:border-gray-700 p-4">
                                        <x-code-block language="markdown" maxHeight="300px">{{ $agentsMd }}</x-code-block>
                                    </div>
                                @endif
                            </div>

                            {{-- heartbeat.md (note) --}}
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700">
                                <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    <code class="text-xs rounded bg-stone-100 px-1.5 py-0.5 dark:bg-gray-900">{{ $workspacePath }}/skills/mission-control-heartbeat/SKILL.md</code>
                                    <span class="ml-2 text-xs italic">Auto-generated when you install skills in Step 4. Skip for now.</span>
                                </div>
                            </div>

                            {{-- .env (note) --}}
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700">
                                <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    <code class="text-xs rounded bg-stone-100 px-1.5 py-0.5 dark:bg-gray-900">{{ $workspacePath }}/.env</code>
                                    <span class="ml-2 text-xs italic">Agent-specific environment variables (token). Created in Step 5.</span>
                                </div>
                            </div>

                            {{-- memory.md --}}
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700">
                                <button
                                    wire:click="toggleFile('memory')"
                                    class="flex w-full items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                >
                                    <span><code class="text-xs rounded bg-stone-100 px-1.5 py-0.5 dark:bg-gray-900">{{ $workspacePath }}/memory.md</code></span>
                                    <x-heroicon-o-chevron-down class="h-4 w-4 transition-transform {{ $expandedFile === 'memory' ? 'rotate-180' : '' }}" />
                                </button>
                                @if ($expandedFile === 'memory')
                                    <div class="border-t border-gray-200 dark:border-gray-700 p-4">
                                        <x-code-block language="markdown" maxHeight="300px">{{ $memoryMd }}</x-code-block>
                                    </div>
                                @endif
                            </div>

                            {{-- user.md --}}
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700">
                                <button
                                    wire:click="toggleFile('user')"
                                    class="flex w-full items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                >
                                    <span><code class="text-xs rounded bg-stone-100 px-1.5 py-0.5 dark:bg-gray-900">{{ $workspacePath }}/user.md</code></span>
                                    <x-heroicon-o-chevron-down class="h-4 w-4 transition-transform {{ $expandedFile === 'user' ? 'rotate-180' : '' }}" />
                                </button>
                                @if ($expandedFile === 'user')
                                    <div class="border-t border-gray-200 dark:border-gray-700 p-4">
                                        <x-code-block language="markdown" maxHeight="300px">{{ $userMd }}</x-code-block>
                                    </div>
                                @endif
                            </div>

                            {{-- tools.md --}}
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700">
                                <button
                                    wire:click="toggleFile('tools')"
                                    class="flex w-full items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                >
                                    <span><code class="text-xs rounded bg-stone-100 px-1.5 py-0.5 dark:bg-gray-900">{{ $workspacePath }}/tools.md</code></span>
                                    <x-heroicon-o-chevron-down class="h-4 w-4 transition-transform {{ $expandedFile === 'tools' ? 'rotate-180' : '' }}" />
                                </button>
                                @if ($expandedFile === 'tools')
                                    <div class="border-t border-gray-200 dark:border-gray-700 p-4">
                                        <x-code-block language="markdown" maxHeight="300px">{{ $toolsMd }}</x-code-block>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- Step 4: Install Skills --}}
        <section>
            <div class="flex items-center gap-3 mb-4">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold"
                    style="background-color: #e0e7ff; color: #4f46e5;"
                >4</div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Install Mission Control Skills</h3>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden" style="margin-left: 44px;">
                {{-- Tabs --}}
                <div class="flex border-b border-gray-200 dark:border-gray-700">
                    <button
                        wire:click="setSkillTab('ask')"
                        class="flex-1 px-4 py-3 text-sm font-medium transition-colors text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                        @if ($skillTab === 'ask') style="color: #4f46e5; border-bottom: 2px solid #4f46e5;" @endif
                    >
                        Option A: Ask Your Agent
                    </button>
                    <button
                        wire:click="setSkillTab('manual')"
                        class="flex-1 px-4 py-3 text-sm font-medium transition-colors text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                        @if ($skillTab === 'manual') style="color: #4f46e5; border-bottom: 2px solid #4f46e5;" @endif
                    >
                        Option B: Manual Setup
                    </button>
                </div>

                <div style="padding: 20px 32px;">
                    @if ($skillTab === 'ask')
                        <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                            Paste this into a chat with your {{ $leadAgent ? 'main agent (' : '' }}<strong>{{ $leadAgent ? $leadAgent->name : $agent->name }}</strong>{{ $leadAgent ? ')' : ' agent' }}:
                        </p>
                        <x-code-block>Create two new skill files in the {{ $agent->name }} agent workspace:

1. Create `{{ $workspacePath }}/skills/mission-control-heartbeat/SKILL.md` with the content below.
2. Create `{{ $workspacePath }}/skills/mission-control-tasks/SKILL.md` with the content below.</x-code-block>

                        <div class="mt-4 space-y-2">
                            {{-- Collapsible: heartbeat skill --}}
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700">
                                <button
                                    wire:click="toggleSkill('heartbeat')"
                                    class="flex w-full items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                >
                                    <span>View mission-control-heartbeat SKILL.md content</span>
                                    <x-heroicon-o-chevron-down class="h-4 w-4 transition-transform {{ $expandedSkill === 'heartbeat' ? 'rotate-180' : '' }}" />
                                </button>
                                @if ($expandedSkill === 'heartbeat')
                                    <div class="border-t border-gray-200 dark:border-gray-700 p-4">
                                        <x-code-block language="markdown" maxHeight="300px">{{ $heartbeatSkillMd }}</x-code-block>
                                    </div>
                                @endif
                            </div>

                            {{-- Collapsible: tasks skill --}}
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700">
                                <button
                                    wire:click="toggleSkill('tasks')"
                                    class="flex w-full items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                >
                                    <span>View mission-control-tasks SKILL.md content</span>
                                    <x-heroicon-o-chevron-down class="h-4 w-4 transition-transform {{ $expandedSkill === 'tasks' ? 'rotate-180' : '' }}" />
                                </button>
                                @if ($expandedSkill === 'tasks')
                                    <div class="border-t border-gray-200 dark:border-gray-700 p-4">
                                        <x-code-block language="markdown" maxHeight="300px">{{ $tasksSkillMd }}</x-code-block>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                            Create the skill directories and files manually:
                        </p>
                        <x-code-block label="Create directories">mkdir -p {{ $workspacePath }}/skills/mission-control-heartbeat
mkdir -p {{ $workspacePath }}/skills/mission-control-tasks</x-code-block>

                        <div class="mt-4 space-y-2">
                            {{-- Collapsible: heartbeat skill --}}
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700">
                                <button
                                    wire:click="toggleSkill('heartbeat')"
                                    class="flex w-full items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                >
                                    <span>View mission-control-heartbeat/SKILL.md</span>
                                    <x-heroicon-o-chevron-down class="h-4 w-4 transition-transform {{ $expandedSkill === 'heartbeat' ? 'rotate-180' : '' }}" />
                                </button>
                                @if ($expandedSkill === 'heartbeat')
                                    <div class="border-t border-gray-200 dark:border-gray-700 p-4">
                                        <x-code-block language="markdown" maxHeight="300px">{{ $heartbeatSkillMd }}</x-code-block>
                                    </div>
                                @endif
                            </div>

                            {{-- Collapsible: tasks skill --}}
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700">
                                <button
                                    wire:click="toggleSkill('tasks')"
                                    class="flex w-full items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                >
                                    <span>View mission-control-tasks/SKILL.md</span>
                                    <x-heroicon-o-chevron-down class="h-4 w-4 transition-transform {{ $expandedSkill === 'tasks' ? 'rotate-180' : '' }}" />
                                </button>
                                @if ($expandedSkill === 'tasks')
                                    <div class="border-t border-gray-200 dark:border-gray-700 p-4">
                                        <x-code-block language="markdown" maxHeight="300px">{{ $tasksSkillMd }}</x-code-block>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- Step 5: Environment Variables --}}
        <section>
            <div class="flex items-center gap-3 mb-4">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold"
                    style="background-color: #e0e7ff; color: #4f46e5;"
                >5</div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Configure Environment Variables</h3>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden" style="margin-left: 44px;">

                @php
                    $tokenValue = $plainToken ?? 'YOUR_TOKEN_HERE';
                @endphp

                {{-- Token Warning / Regenerate — always visible above everything --}}
                <div style="padding: 20px 32px 0 32px;">
                    @if ($plainToken)
                        <div class="flex items-start gap-3" style="background-color: #fffbeb; border: 1px solid #f59e0b; border-radius: 8px; padding: 12px 16px;">
                            <x-heroicon-o-exclamation-triangle class="h-5 w-5 flex-shrink-0 mt-0.5" style="color: #f59e0b;" />
                            <div>
                                <p style="color: #92400e; font-weight: 600;">Save this token now — it won't be shown again.</p>
                                <p class="mt-1 text-sm" style="color: #92400e;">Copy the environment variables below and store them somewhere safe. Once you leave this page, the token cannot be retrieved.</p>
                            </div>
                        </div>
                    @else
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                            <div class="flex items-center gap-3 mb-3">
                                <x-heroicon-o-key class="h-5 w-5" style="color: #f59e0b;" />
                                <p class="text-sm text-gray-700 dark:text-gray-300">Token not available — it was shown once at creation.</p>
                            </div>
                            <x-filament::button
                                wire:click="regenerateToken"
                                wire:confirm="This will invalidate the current token. Any connected agents using it will need to be updated. Continue?"
                                color="warning"
                                icon="heroicon-o-arrow-path"
                            >
                                Regenerate Token
                            </x-filament::button>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                This will create a new token and invalidate the existing one.
                            </p>
                        </div>
                    @endif
                </div>

                {{-- Context --}}
                <div style="padding: 16px 32px 0 32px;">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Each agent needs its own API token. Since all your agents share the same OpenClaw gateway, we store tokens in each agent's workspace directory so they don't collide.
                    </p>
                </div>

                {{-- Sub-section A: API URL (shared) --}}
                <div style="padding: 16px 32px 0 32px;">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">API URL (one-time, shared across all agents)</h4>
                    <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                        If you haven't already, set the Mission Control API URL in your global OpenClaw environment. This only needs to be done once — all agents share it.
                    </p>
                    <x-code-block language="bash"># Skip this if you've already set MC_API_URL for another agent
echo 'MC_API_URL={{ $apiUrl }}' >> ~/.openclaw/.env</x-code-block>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        This goes in <code class="rounded bg-stone-100 px-1.5 py-0.5 text-xs dark:bg-gray-900">~/.openclaw/.env</code> which is loaded by all agents. If it's already there from setting up a previous agent, skip this.
                    </p>
                </div>

                {{-- Sub-section B: Agent Token (per-agent) --}}
                <div style="padding: 16px 32px 0 32px;">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Agent Token (per-agent, in workspace .env)</h4>
                    <p class="mb-0 text-sm text-gray-600 dark:text-gray-300">
                        Store this agent's token in its workspace <code class="rounded bg-stone-100 px-1.5 py-0.5 text-xs dark:bg-gray-900">.env</code> file. This ensures each agent uses its own token.
                    </p>
                </div>

                {{-- Tabs for token storage --}}
                <div class="flex border-b border-gray-200 dark:border-gray-700 mt-4">
                    <button
                        wire:click="setEnvTab('dotenv')"
                        class="flex-1 px-3 py-3 text-xs font-medium transition-colors text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                        @if ($envTab === 'dotenv') style="color: #4f46e5; border-bottom: 2px solid #4f46e5;" @endif
                    >
                        Workspace .env (Recommended)
                    </button>
                    <button
                        wire:click="setEnvTab('agent')"
                        class="flex-1 px-3 py-3 text-xs font-medium transition-colors text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                        @if ($envTab === 'agent') style="color: #4f46e5; border-bottom: 2px solid #4f46e5;" @endif
                    >
                        Ask Your Agent
                    </button>
                </div>

                <div style="padding: 20px 32px;">
                    @if ($envTab === 'dotenv')
                        <x-code-block language="bash">echo 'MC_AGENT_TOKEN={{ $tokenValue }}' >> {{ $workspacePath }}/.env</x-code-block>
                        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                            This creates (or appends to) a <code class="rounded bg-stone-100 px-1.5 py-0.5 text-xs dark:bg-gray-900">.env</code> file in <code class="rounded bg-stone-100 px-1.5 py-0.5 text-xs dark:bg-gray-900">{{ $workspacePath }}/</code>. When {{ $agent->name }} runs, it loads this file automatically because the workspace is its working directory.
                        </p>

                    @elseif ($envTab === 'agent')
                        <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                            Paste this into a chat with your {{ $leadAgent ? 'main agent (' : '' }}<strong>{{ $leadAgent ? $leadAgent->name : $agent->name }}</strong>{{ $leadAgent ? ')' : ' agent' }}:
                        </p>
                        <x-code-block>Add the Mission Control token to the {{ $agent->name }} agent's workspace environment file.

Append this line to {{ $workspacePath }}/.env (create the file if it doesn't exist):

MC_AGENT_TOKEN={{ $tokenValue }}

Don't overwrite the file — append to it. Also make sure MC_API_URL={{ $apiUrl }} exists in ~/.openclaw/.env (add it if it's not already there).</x-code-block>
                    @endif

                    @if (! $plainToken && $envTab !== 'agent')
                        <p class="mt-3 text-xs" style="color: #d97706;">
                            <strong>Note:</strong> Replace <code class="rounded px-1 py-0.5 text-xs" style="background-color: #fef3c7;">YOUR_TOKEN_HERE</code> with the token you saved when the agent was created, or regenerate one above.
                        </p>
                    @endif
                </div>
            </div>
        </section>

        {{-- Step 6: Cron Configuration --}}
        <section>
            <div class="flex items-center gap-3 mb-4">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold"
                    style="background-color: #e0e7ff; color: #4f46e5;"
                >6</div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Configure Heartbeat Cron</h3>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden" style="margin-left: 44px;">
                {{-- Tabs --}}
                <div class="flex border-b border-gray-200 dark:border-gray-700">
                    <button
                        wire:click="setSkillTab('ask')"
                        class="flex-1 px-4 py-3 text-sm font-medium transition-colors text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                        @if ($skillTab === 'ask') style="color: #4f46e5; border-bottom: 2px solid #4f46e5;" @endif
                    >
                        Option A: Ask Your Agent
                    </button>
                    <button
                        wire:click="setSkillTab('manual')"
                        class="flex-1 px-4 py-3 text-sm font-medium transition-colors text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                        @if ($skillTab === 'manual') style="color: #4f46e5; border-bottom: 2px solid #4f46e5;" @endif
                    >
                        Option B: Manual Setup
                    </button>
                </div>

                <div style="padding: 20px 32px;">
                    @if ($skillTab === 'ask')
                        <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                            Paste this into a chat with your {{ $leadAgent ? 'main agent (' : '' }}<strong>{{ $leadAgent ? $leadAgent->name : $agent->name }}</strong>{{ $leadAgent ? ')' : ' agent' }}:
                        </p>
                        <x-code-block>Add a heartbeat cron to the openclaw.json configuration at ~/.openclaw/openclaw.json.

In the agent config for "{{ $agentSlug }}", add a "crons" array with this entry:

{{ $cronOnlyConfig }}

Add the crons array to the existing agent entry — don't create a new agent or duplicate the config.</x-code-block>
                    @else
                        <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                            The heartbeat is a cron job that makes your agent check in with Mission Control on a schedule.
                            It uses <strong>{{ str_replace('anthropic/', '', $heartbeatModel) }}</strong> (a cheap, fast model) so it costs almost nothing to run.
                        </p>

                        {{-- Sub-section: If you added in Step 2 --}}
                        <div class="mb-6">
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">If you added this agent in Step 2 (openclaw.json):</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">
                                The cron was already included in your agent config from Step 2 — you can skip this step.
                                To verify, open <code class="rounded bg-stone-100 px-1.5 py-0.5 text-xs dark:bg-gray-900">~/.openclaw/openclaw.json</code> and check that your agent has a <code class="rounded bg-stone-100 px-1.5 py-0.5 text-xs dark:bg-gray-900">crons</code> array with a "Mission Control Heartbeat" entry.
                            </p>

                            {{-- Collapsible: Verify cron config --}}
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700">
                                <button
                                    wire:click="toggleFile('cronVerify')"
                                    class="flex w-full items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                >
                                    <span>View cron config to verify</span>
                                    <x-heroicon-o-chevron-down class="h-4 w-4 transition-transform {{ $expandedFile === 'cronVerify' ? 'rotate-180' : '' }}" />
                                </button>
                                @if ($expandedFile === 'cronVerify')
                                    <div class="border-t border-gray-200 dark:border-gray-700 p-4">
                                        <x-code-block language="json" maxHeight="200px">{{ $cronOnlyConfig }}</x-code-block>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Sub-section: If you set up manually --}}
                        <div>
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">If you set up this agent manually:</h4>
                            <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                                Open <code class="rounded bg-stone-100 px-1.5 py-0.5 text-xs dark:bg-gray-900">~/.openclaw/openclaw.json</code>, find your agent entry by name, and add a <code class="rounded bg-stone-100 px-1.5 py-0.5 text-xs dark:bg-gray-900">crons</code> key to it:
                            </p>

                            <x-code-block language="json">{{ $cronConfigInContext }}</x-code-block>

                            <div class="mt-3 rounded-lg p-3" style="background-color: #f0f9ff; border: 1px solid #bae6fd;">
                                <p class="text-xs" style="color: #0369a1;">
                                    <strong>Note:</strong> Add the <code class="text-xs">crons</code> key inside your existing agent object — don't create a new agent entry.
                                    Heartbeats use {{ str_replace('anthropic/', '', $heartbeatModel) }} to check for work.
                                    Actual tasks will use the agent's work model ({{ str_replace('anthropic/', '', $agent->work_model ?? 'default') }}).
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- Step 7: SOUL.md --}}
        <section>
            <div class="flex items-center gap-3 mb-4">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold"
                    style="background-color: #e0e7ff; color: #4f46e5;"
                >7</div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Set Up Agent Identity (SOUL.md)</h3>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden" style="margin-left: 44px;">
                @if ($agent->soul_md)
                    {{-- Edit button bar --}}
                    <div class="flex items-center justify-between" style="padding: 16px 32px 0 32px;">
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            Save this SOUL.md to your agent's workspace. Changes made in the dashboard will sync to your agent on the next heartbeat.
                        </p>
                        <a href="{{ \App\Filament\Resources\AgentResource::getUrl('edit', ['record' => $agent]) }}" class="flex-shrink-0 ml-4 inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors" style="color: #4f46e5; border: 1px solid #c7d2fe;">
                            <x-heroicon-o-pencil-square class="h-3.5 w-3.5" />
                            Edit
                        </a>
                    </div>

                    {{-- Tabs --}}
                    <div class="flex border-b border-gray-200 dark:border-gray-700 mt-4">
                        <button
                            wire:click="setSkillTab('ask')"
                            class="flex-1 px-4 py-3 text-sm font-medium transition-colors text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                            @if ($skillTab === 'ask') style="color: #4f46e5; border-bottom: 2px solid #4f46e5;" @endif
                        >
                            Option A: Ask Your Agent
                        </button>
                        <button
                            wire:click="setSkillTab('manual')"
                            class="flex-1 px-4 py-3 text-sm font-medium transition-colors text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                            @if ($skillTab === 'manual') style="color: #4f46e5; border-bottom: 2px solid #4f46e5;" @endif
                        >
                            Option B: Manual Setup
                        </button>
                    </div>

                    <div style="padding: 20px 32px;">
                        @if ($skillTab === 'ask')
                            <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                                Paste this into a chat with your {{ $leadAgent ? 'main agent (' : '' }}<strong>{{ $leadAgent ? $leadAgent->name : $agent->name }}</strong>{{ $leadAgent ? ')' : ' agent' }}:
                            </p>
                            <x-code-block>Save this as the SOUL.md file at {{ $workspacePath }}/SOUL.md:

{{ $agent->soul_md }}</x-code-block>
                        @else
                            <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                                Create the SOUL.md file in your agent's workspace:
                            </p>
                            <x-code-block language="bash">cat > {{ $workspacePath }}/SOUL.md << 'SOUL_EOF'
{{ $agent->soul_md }}
SOUL_EOF</x-code-block>
                            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                This creates (or overwrites) the SOUL.md file at <code class="rounded bg-stone-100 px-1.5 py-0.5 text-xs dark:bg-gray-900">{{ $workspacePath }}/SOUL.md</code>. To edit it later, open the file in any text editor or update it from the Mission Control dashboard using the Edit button above.
                            </p>
                        @endif
                    </div>

                    {{-- SOUL.md preview --}}
                    <div style="padding: 0 32px 20px 32px;">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">SOUL.md for {{ $agent->name }}</div>
                        <x-code-block language="markdown" maxHeight="300px">{{ $agent->soul_md }}</x-code-block>
                    </div>
                @else
                    <div style="padding: 20px 32px;">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            No SOUL.md configured yet. You can add one in the
                            <a href="{{ \App\Filament\Resources\AgentResource::getUrl('edit', ['record' => $agent]) }}" class="font-medium underline" style="color: #4f46e5;">agent settings</a>.
                        </p>
                    </div>
                @endif
            </div>
        </section>

        {{-- Step 8: Test Connection --}}
        <section>
            <div class="flex items-center gap-3 mb-4">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold"
                    style="background-color: #e0e7ff; color: #4f46e5;"
                >8</div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Test the Connection</h3>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden" style="margin-left: 44px;">
                {{-- Tabs --}}
                <div class="flex border-b border-gray-200 dark:border-gray-700">
                    <button
                        wire:click="setSkillTab('ask')"
                        class="flex-1 px-4 py-3 text-sm font-medium transition-colors text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                        @if ($skillTab === 'ask') style="color: #4f46e5; border-bottom: 2px solid #4f46e5;" @endif
                    >
                        Option A: Run Heartbeat from Agent
                    </button>
                    <button
                        wire:click="setSkillTab('manual')"
                        class="flex-1 px-4 py-3 text-sm font-medium transition-colors text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                        @if ($skillTab === 'manual') style="color: #4f46e5; border-bottom: 2px solid #4f46e5;" @endif
                    >
                        Option B: Manual curl Test
                    </button>
                </div>

                <div style="padding: 20px 32px;">
                    @if ($skillTab === 'ask')
                        @if ($agent->is_lead)
                            <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                                The best way to test is to have your agent actually run the heartbeat. This proves the agent is fully configured with skills, env vars, and cron. Paste this into a chat with your <strong>{{ $agent->name }}</strong> agent:
                            </p>

                            <x-code-block>Run the mission-control-heartbeat skill now. Sync with Mission Control, report your status, and check for pending work.</x-code-block>
                        @elseif ($leadAgent)
                            <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                                The best way to test is to have the agent actually run the heartbeat. Paste this into a chat with your main agent (<strong>{{ $leadAgent->name }}</strong>). It will tell {{ $agent->name }} to run its heartbeat:
                            </p>

                            <x-code-block>Tell {{ $agent->name }} to run the mission-control-heartbeat skill now. It should sync with Mission Control, report its status, and check for pending work.</x-code-block>
                        @else
                            <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                                The best way to test is to have your agent actually run the heartbeat. This proves the agent is fully configured with skills, env vars, and cron. Paste this into a chat with your <strong>{{ $agent->name }}</strong> agent:
                            </p>

                            <x-code-block>Run the mission-control-heartbeat skill now. Sync with Mission Control, report your status, and check for pending work.</x-code-block>
                        @endif

                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                            This is the exact message your heartbeat cron will send on every trigger. If the agent can do it manually, the cron will work too. Watch the widget below — it should turn green within a few seconds.
                        </p>

                        <div class="mt-4 rounded-lg p-3" style="background-color: #f0f9ff; border: 1px solid #bae6fd;">
                            <p class="text-xs" style="color: #0369a1;">
                                Or, if you already configured the cron in Step 6, just wait for the next scheduled run. The cron fires every {{ intval($heartbeatInterval / 60) }} minutes.
                            </p>
                        </div>
                    @else
                        <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                            If you want to test the API connection directly (without going through the agent), run this curl command:
                        </p>

                        <x-code-block>{{ $curlCommand }}</x-code-block>

                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                            This sources your agent's workspace <code class="rounded bg-stone-100 px-1.5 py-0.5 text-xs dark:bg-gray-900">.env</code> to load <code class="rounded bg-stone-100 px-1.5 py-0.5 text-xs dark:bg-gray-900">MC_AGENT_TOKEN</code>, then runs the curl command. The <code class="rounded bg-stone-100 px-1.5 py-0.5 text-xs dark:bg-gray-900">MC_API_URL</code> is loaded from <code class="rounded bg-stone-100 px-1.5 py-0.5 text-xs dark:bg-gray-900">~/.openclaw/.env</code>.
                        </p>

                        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                            This confirms the API URL and token are correct, but doesn't verify that your agent's skills and cron are properly configured. Use Option A for a full end-to-end test.
                        </p>
                    @endif
                </div>

                {{-- Expected response — visible in both tabs --}}
                <div style="padding: 0 32px 20px 32px;">
                    <div class="rounded-lg p-4" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                        <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Expected response:</p>
                        <pre class="text-xs text-gray-600 dark:text-gray-300" style="white-space: pre-wrap;">{{ json_encode(['status' => 'ok', 'notifications' => [], 'tasks' => [], 'config' => ['heartbeat_interval_seconds' => $heartbeatInterval]], JSON_PRETTY_PRINT) }}</pre>
                    </div>
                </div>
            </div>
        </section>

        {{-- Connection Status Widget --}}
        <section style="margin-left: 44px;">
            <livewire:connection-status-widget :agent="$agent" />
        </section>

    </div>
    </div>
</x-filament-panels::page>
