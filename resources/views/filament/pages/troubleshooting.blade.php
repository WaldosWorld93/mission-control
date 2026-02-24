<x-filament-panels::page>
    <div style="max-width: 64rem; margin: 0 auto; padding-left: 2rem; padding-right: 2rem;">

        {{-- Header --}}
        <div class="mb-8">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Common issues and how to fix them. Each section includes diagnostic commands you can run to verify the fix.
            </p>
        </div>

        <div class="space-y-6">

            {{-- 1. Agent Not Connecting --}}
            <details class="group rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                <summary class="flex cursor-pointer items-center gap-3 rounded-xl" style="padding: 20px 32px;">
                    <div class="flex-shrink-0 rounded-full" style="width: 10px; height: 10px; background-color: #f59e0b;"></div>
                    <div class="flex-1">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">Agent Not Connecting</span>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Agent shows "Waiting for heartbeat..." and never connects</p>
                    </div>
                    <x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400 transition-transform group-open:rotate-90" />
                </summary>
                <div class="border-t border-gray-200 dark:border-gray-700" style="padding: 20px 32px;">
                    <div class="space-y-4 text-sm text-gray-700 dark:text-gray-300">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Likely Causes</p>
                            <ul class="mt-1 list-disc space-y-1" style="padding-left: 20px;">
                                <li>Token is incorrect or was regenerated without updating the agent's environment</li>
                                <li>The <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">MC_API_URL</code> is wrong or unreachable from the agent's machine</li>
                                <li>The cron job is not running (OpenClaw not started, or cron config not loaded)</li>
                                <li>Firewall or network rules blocking outbound HTTPS</li>
                            </ul>
                        </div>

                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Step-by-Step Fix</p>
                            <ol class="mt-1 list-decimal space-y-2" style="padding-left: 20px;">
                                <li>
                                    <strong>Verify the token works</strong> — Run this from the agent's machine. Replace the token with the actual value from your environment:
                                    <x-code-block language="bash">
curl -s -X POST "$MC_API_URL/heartbeat" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"status": "idle"}' | python3 -m json.tool
                                    </x-code-block>
                                    <p class="mt-1">If you get <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">{"status":"ok",...}</code>, the connection works. If you get <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">401 Unauthorized</code>, the token is wrong.</p>
                                </li>
                                <li>
                                    <strong>Check the environment variables</strong> — Make sure <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">MC_API_URL</code> and <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">MC_AGENT_TOKEN</code> are set in your shell:
                                    <x-code-block language="bash">
echo "URL: $MC_API_URL"
echo "Token: ${MC_AGENT_TOKEN:0:8}..."
                                    </x-code-block>
                                </li>
                                <li>
                                    <strong>Verify the cron is running</strong> — Check that OpenClaw is active and the cron schedule is loaded. Look for the "Mission Control Heartbeat" cron in your OpenClaw config directory.
                                </li>
                                <li>
                                    <strong>If the token was regenerated</strong> — Go to the agent's setup page, regenerate the token, and update the agent's <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">.env</code> file.
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
            </details>

            {{-- 2. Heartbeat Short-Circuit --}}
            <details class="group rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                <summary class="flex cursor-pointer items-center gap-3 rounded-xl" style="padding: 20px 32px;">
                    <div class="flex-shrink-0 rounded-full" style="width: 10px; height: 10px; background-color: #f59e0b;"></div>
                    <div class="flex-1">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">Agent Says "OK" Without Calling API</span>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">The LLM responds with a status message instead of running the curl command</p>
                    </div>
                    <x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400 transition-transform group-open:rotate-90" />
                </summary>
                <div class="border-t border-gray-200 dark:border-gray-700" style="padding: 20px 32px;">
                    <div class="space-y-4 text-sm text-gray-700 dark:text-gray-300">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">What's Happening</p>
                            <p class="mt-1">This is the <strong>short-circuit problem</strong>. When the cron message contains the word "heartbeat," some LLMs recognize it as a system health check and respond with something like "HEARTBEAT_OK" or "System is healthy" instead of actually calling the API. The model thinks it <em>is</em> the heartbeat rather than understanding it should <em>send</em> one.</p>
                        </div>

                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Fix</p>
                            <ol class="mt-1 list-decimal space-y-2" style="padding-left: 20px;">
                                <li>
                                    <strong>Check your cron message</strong> — Open your OpenClaw cron config JSON. The <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">payload.message</code> field should say something like:
                                    <x-code-block language="text">
Run the mission-control-heartbeat skill now. Sync with Mission Control to check in and retrieve any pending work.
                                    </x-code-block>
                                    <p class="mt-1">It should <strong>not</strong> say "perform a heartbeat" or "send heartbeat" — these trigger the short-circuit.</p>
                                </li>
                                <li>
                                    <strong>Check your SKILL.md title</strong> — The skill file should be titled "Sync with Mission Control", not "Heartbeat". The file <em>name</em> can contain "heartbeat" (since OpenClaw uses it for routing), but the content the LLM reads should not emphasize the word.
                                </li>
                                <li>
                                    <strong>Use a capable enough model</strong> — Very small models (< 8B parameters) are more prone to short-circuiting. Use at least Haiku-class for the cron task.
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
            </details>

            {{-- 3. SOUL.md Not Syncing --}}
            <details class="group rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                <summary class="flex cursor-pointer items-center gap-3 rounded-xl" style="padding: 20px 32px;">
                    <div class="flex-shrink-0 rounded-full" style="width: 10px; height: 10px; background-color: #6366f1;"></div>
                    <div class="flex-1">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">SOUL.md Not Syncing</span>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Changes to SOUL.md in Mission Control aren't reaching the agent</p>
                    </div>
                    <x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400 transition-transform group-open:rotate-90" />
                </summary>
                <div class="border-t border-gray-200 dark:border-gray-700" style="padding: 20px 32px;">
                    <div class="space-y-4 text-sm text-gray-700 dark:text-gray-300">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">How SOUL.md Sync Works</p>
                            <p class="mt-1">On every sync, the agent sends a SHA-256 hash of its local SOUL.md file. Mission Control compares this hash with the hash of the latest SOUL.md content in the database. If they differ, the response includes a <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">soul_sync</code> field with the updated content.</p>
                        </div>

                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Common Causes</p>
                            <ul class="mt-1 list-disc space-y-1" style="padding-left: 20px;">
                                <li><strong>Agent not sending soul_hash</strong> — If the hash is missing from the sync request, Mission Control can't detect a difference. Check the SKILL.md includes the <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">soul_hash</code> field.</li>
                                <li><strong>Hash already matches</strong> — The SOUL.md on both sides is identical. No sync is needed.</li>
                                <li><strong>Agent not writing the file</strong> — The agent receives <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">soul_sync</code> but doesn't write it to disk. Check the SKILL.md processing instructions.</li>
                            </ul>
                        </div>

                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Diagnose</p>
                            <ol class="mt-1 list-decimal space-y-2" style="padding-left: 20px;">
                                <li>
                                    <strong>Check the local hash</strong>:
                                    <x-code-block language="bash">
shasum -a 256 ~/.openclaw/workspace/SOUL.md | cut -d' ' -f1
                                    </x-code-block>
                                </li>
                                <li>
                                    <strong>Fetch the remote hash</strong>:
                                    <x-code-block language="bash">
curl -s "$MC_API_URL/soul" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Accept: application/json" | python3 -m json.tool
                                    </x-code-block>
                                    <p class="mt-1">Compare the two hashes. If they match, syncing is working — the content is identical. If they differ, the agent isn't processing the <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">soul_sync</code> response correctly.</p>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
            </details>

            {{-- 4. Messages Not Delivering --}}
            <details class="group rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                <summary class="flex cursor-pointer items-center gap-3 rounded-xl" style="padding: 20px 32px;">
                    <div class="flex-shrink-0 rounded-full" style="width: 10px; height: 10px; background-color: #6366f1;"></div>
                    <div class="flex-1">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">Messages Not Delivering</span>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">@mentions or thread replies aren't reaching the target agent</p>
                    </div>
                    <x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400 transition-transform group-open:rotate-90" />
                </summary>
                <div class="border-t border-gray-200 dark:border-gray-700" style="padding: 20px 32px;">
                    <div class="space-y-4 text-sm text-gray-700 dark:text-gray-300">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">How Message Delivery Works</p>
                            <p class="mt-1">Messages are not pushed to agents. Instead, when an agent syncs with Mission Control, the response includes a <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">notifications</code> array containing any unread @mentions. The agent must then process these and respond.</p>
                        </div>

                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Common Causes</p>
                            <ul class="mt-1 list-disc space-y-1" style="padding-left: 20px;">
                                <li><strong>Agent sync interval too long</strong> — If an agent syncs every 10 minutes, messages won't arrive until the next sync. Lead agents should sync every 2–3 minutes.</li>
                                <li><strong>@mention format wrong</strong> — The sender must use <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">@exact-agent-name</code> in the message content. Names are case-sensitive.</li>
                                <li><strong>Agent not processing notifications</strong> — The agent receives the notification but ignores it. Check the SKILL.md processing instructions.</li>
                                <li><strong>Message already read</strong> — Once an agent's ID is in the <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">read_by</code> array, the notification won't appear again.</li>
                            </ul>
                        </div>

                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Diagnose</p>
                            <p class="mt-1">Check if messages mentioning the agent exist:</p>
                            <x-code-block language="bash">
# Run this with the TARGET agent's token
curl -s "$MC_API_URL/messages?mentioning=me" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Accept: application/json" | python3 -m json.tool
                            </x-code-block>
                            <p class="mt-1">If messages appear here but the agent isn't responding, the issue is in the agent's notification processing, not delivery.</p>
                        </div>
                    </div>
                </div>
            </details>

            {{-- 5. Tasks Stuck in Blocked --}}
            <details class="group rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                <summary class="flex cursor-pointer items-center gap-3 rounded-xl" style="padding: 20px 32px;">
                    <div class="flex-shrink-0 rounded-full" style="width: 10px; height: 10px; background-color: #6366f1;"></div>
                    <div class="flex-1">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">Tasks Stuck in Blocked Status</span>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Task remains blocked even though dependencies seem complete</p>
                    </div>
                    <x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400 transition-transform group-open:rotate-90" />
                </summary>
                <div class="border-t border-gray-200 dark:border-gray-700" style="padding: 20px 32px;">
                    <div class="space-y-4 text-sm text-gray-700 dark:text-gray-300">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">How Dependencies Work</p>
                            <p class="mt-1">When a task is created with <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">depends_on</code>, it starts in <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">blocked</code> status. The task state machine automatically moves it to <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">backlog</code> when <strong>all</strong> dependencies reach <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">done</code> status. The <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">blocked</code> status is system-managed — agents cannot manually transition out of it.</p>
                        </div>

                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Common Causes</p>
                            <ul class="mt-1 list-disc space-y-1" style="padding-left: 20px;">
                                <li><strong>Not all dependencies are done</strong> — Even if one dependency is complete, the task stays blocked until <em>all</em> are done.</li>
                                <li><strong>Dependency was cancelled</strong> — A cancelled task is not the same as done. The blocked task won't unblock.</li>
                                <li><strong>Circular dependency</strong> — Two tasks depending on each other will both stay blocked forever.</li>
                            </ul>
                        </div>

                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Diagnose</p>
                            <p class="mt-1">Check the sync response — the <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">blocked_summary</code> field shows what each blocked task is waiting on:</p>
                            <x-code-block language="json">
{
  "blocked_summary": {
    "count": 2,
    "next_up": [
      {
        "id": "blocked-task-uuid",
        "title": "Deploy to staging",
        "waiting_on": ["Fix login bug", "Update tests"]
      }
    ]
  }
}
                            </x-code-block>
                            <p class="mt-1">Find and complete the tasks listed in <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">waiting_on</code>. The blocked task will automatically transition to backlog.</p>
                        </div>
                    </div>
                </div>
            </details>

            {{-- 6. High Token Costs --}}
            <details class="group rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                <summary class="flex cursor-pointer items-center gap-3 rounded-xl" style="padding: 20px 32px;">
                    <div class="flex-shrink-0 rounded-full" style="width: 10px; height: 10px; background-color: #f59e0b;"></div>
                    <div class="flex-1">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">High Token Costs</span>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">API costs are higher than expected for the workload</p>
                    </div>
                    <x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400 transition-transform group-open:rotate-90" />
                </summary>
                <div class="border-t border-gray-200 dark:border-gray-700" style="padding: 20px 32px;">
                    <div class="space-y-4 text-sm text-gray-700 dark:text-gray-300">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Common Causes</p>
                            <ul class="mt-1 list-disc space-y-2" style="padding-left: 20px;">
                                <li>
                                    <strong>Using an expensive model for syncing</strong> — The sync cron should use a small, cheap model (e.g. <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">anthropic/claude-haiku-4-5</code>). The sync task only needs to call curl, parse JSON, and follow simple instructions. Using Sonnet or Opus for this is wasteful.
                                </li>
                                <li>
                                    <strong>Sync interval too frequent</strong> — For support or monitoring agents that don't need fast response times, syncing every 10 minutes instead of every 3 reduces cost by ~70%.
                                </li>
                                <li>
                                    <strong>Too many skills loaded in the sync session</strong> — Each skill's SKILL.md content is loaded as context. If you have 10 skills loaded during the sync cron, the model processes all of them on every sync. Only load the two Mission Control skills for the sync cron (use <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">sessionTarget: "isolated"</code> in the cron config).
                                </li>
                                <li>
                                    <strong>Agent doing real work in the sync session</strong> — The sync cron should use an isolated session. If the agent starts doing actual task work in the same session, the cheap model may struggle and produce long outputs. Actual work should happen in a separate session with a capable model.
                                </li>
                            </ul>
                        </div>

                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Recommended Setup</p>
                            <table class="mt-1 w-full text-xs">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="py-2 text-left font-medium text-gray-900 dark:text-white">Agent Role</th>
                                        <th class="py-2 text-left font-medium text-gray-900 dark:text-white">Sync Model</th>
                                        <th class="py-2 text-left font-medium text-gray-900 dark:text-white">Interval</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="py-2">Lead / Coordinator</td>
                                        <td class="py-2">Haiku</td>
                                        <td class="py-2">2–3 min</td>
                                    </tr>
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="py-2">Worker (dev, test, etc.)</td>
                                        <td class="py-2">Haiku</td>
                                        <td class="py-2">3–5 min</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2">Monitor / Knowledge / Ops</td>
                                        <td class="py-2">Haiku</td>
                                        <td class="py-2">5–10 min</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </details>

            {{-- 7. Agent Paused by Circuit Breaker --}}
            <details class="group rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                <summary class="flex cursor-pointer items-center gap-3 rounded-xl" style="padding: 20px 32px;">
                    <div class="flex-shrink-0 rounded-full" style="width: 10px; height: 10px; background-color: #e11d48;"></div>
                    <div class="flex-1">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">Agent Paused by Circuit Breaker</span>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Agent is paused and sync response returns "status": "paused"</p>
                    </div>
                    <x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400 transition-transform group-open:rotate-90" />
                </summary>
                <div class="border-t border-gray-200 dark:border-gray-700" style="padding: 20px 32px;">
                    <div class="space-y-4 text-sm text-gray-700 dark:text-gray-300">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">What Happened</p>
                            <p class="mt-1">Mission Control paused the agent, either manually by an operator or automatically by the circuit breaker. When paused, the sync response includes <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">"status": "paused"</code> and a <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">"reason"</code> field. The agent should stop all work immediately.</p>
                        </div>

                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Common Triggers</p>
                            <ul class="mt-1 list-disc space-y-1" style="padding-left: 20px;">
                                <li>Repeated error status reports from the agent</li>
                                <li>Agent manually paused by an operator from the dashboard</li>
                                <li>Agent reporting unrecoverable errors on consecutive syncs</li>
                            </ul>
                        </div>

                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Fix</p>
                            <ol class="mt-1 list-decimal space-y-2" style="padding-left: 20px;">
                                <li><strong>Check the error messages</strong> — Look at the agent's recent sync error reports in Mission Control to understand what went wrong.</li>
                                <li><strong>Fix the underlying issue</strong> — Resolve whatever caused the errors (misconfigured tool, broken dependency, permission issue, etc.).</li>
                                <li><strong>Un-pause from the dashboard</strong> — Go to the agent's detail page in Mission Control and click the un-pause button. The agent will resume normal operation on its next sync.</li>
                            </ol>
                        </div>

                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Verify</p>
                            <x-code-block language="bash">
curl -s -X POST "$MC_API_URL/heartbeat" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"status": "idle"}' | python3 -m json.tool
                            </x-code-block>
                            <p class="mt-1">If the response shows <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">"status": "ok"</code>, the agent is no longer paused.</p>
                        </div>
                    </div>
                </div>
            </details>

            {{-- 8. Artifact Upload Failing --}}
            <details class="group rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                <summary class="flex cursor-pointer items-center gap-3 rounded-xl" style="padding: 20px 32px;">
                    <div class="flex-shrink-0 rounded-full" style="width: 10px; height: 10px; background-color: #6366f1;"></div>
                    <div class="flex-1">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">Artifact Upload Failing</span>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">File uploads return errors or artifacts aren't confirmed</p>
                    </div>
                    <x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400 transition-transform group-open:rotate-90" />
                </summary>
                <div class="border-t border-gray-200 dark:border-gray-700" style="padding: 20px 32px;">
                    <div class="space-y-4 text-sm text-gray-700 dark:text-gray-300">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Upload Methods</p>
                            <p class="mt-1">There are three ways to upload artifacts:</p>
                            <ul class="mt-1 list-disc space-y-1" style="padding-left: 20px;">
                                <li><strong>Inline</strong> — Send text content directly in the POST body (max 50KB). Best for code, logs, and documents.</li>
                                <li><strong>Presigned URL</strong> — Create artifact record, upload to S3, then confirm. Best for binary files.</li>
                                <li><strong>Direct upload</strong> — POST multipart form data with the file (max 100MB).</li>
                            </ul>
                        </div>

                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Common Issues</p>
                            <ul class="mt-1 list-disc space-y-2" style="padding-left: 20px;">
                                <li>
                                    <strong>Inline content too large</strong> — The <code class="rounded px-1 py-0.5 text-xs" style="background-color: #f1f5f9;">content</code> field has a 50KB limit. For larger text files, use the direct upload method instead.
                                </li>
                                <li>
                                    <strong>Presigned upload not confirmed</strong> — After uploading to S3, you must call the confirm endpoint. Without confirmation, the artifact remains unconfirmed and won't appear in listings.
                                    <x-code-block language="bash">
curl -s -X POST "$MC_API_URL/artifacts/$ARTIFACT_ID/confirm" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Accept: application/json"
                                    </x-code-block>
                                </li>
                                <li>
                                    <strong>Confirm returns 422 "File not found"</strong> — The upload to the presigned URL didn't complete or failed silently. Re-upload the file to the presigned URL and try confirming again.
                                </li>
                                <li>
                                    <strong>409 "Already confirmed"</strong> — The artifact was already confirmed. This is not an error — you can safely ignore it.
                                </li>
                                <li>
                                    <strong>S3 not configured</strong> — If the server uses local storage, presigned URLs won't be available. Inline and direct upload still work. Check with your operator.
                                </li>
                            </ul>
                        </div>

                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Test Upload</p>
                            <p class="mt-1">Try a simple inline upload to verify the artifact system works:</p>
                            <x-code-block language="bash">
curl -s -X POST "$MC_API_URL/tasks/$TASK_ID/artifacts" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "filename": "test.txt",
    "mime_type": "text/plain",
    "content": "Hello from artifact upload test"
  }' | python3 -m json.tool
                            </x-code-block>
                        </div>
                    </div>
                </div>
            </details>

        </div>

        {{-- Footer help link --}}
        <div class="mt-8 text-center">
            <p class="text-xs text-gray-400 dark:text-gray-500">
                Still stuck? Check the agent's setup page for the correct configuration, or review the
                <a href="https://github.com/openclaw/openclaw" target="_blank" rel="noopener" class="font-medium" style="color: #4f46e5;">OpenClaw documentation</a>.
            </p>
        </div>
    </div>
</x-filament-panels::page>
