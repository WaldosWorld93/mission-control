<?php

namespace App\Filament\Pages;

use App\Models\Agent;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class AgentSetup extends Page
{
    protected static ?string $slug = 'agents/{agent}/setup';

    protected static ?string $title = 'Agent Setup Guide';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.agent-setup';

    public Agent $agent;

    public ?string $plainToken = null;

    public bool $showSkillOption = false;

    public string $skillTab = 'ask';

    public ?string $expandedSkill = null;

    public ?string $expandedFile = null;

    public bool $tokenIsNew = false;

    public string $envTab = 'dotenv';

    public function mount(Agent $agent): void
    {
        $this->agent = $agent;

        // Check for token stored by agent ID (from create or regenerate)
        $sessionKey = "agent_token_{$agent->id}";
        if (session()->has($sessionKey)) {
            $this->plainToken = session($sessionKey);
            // Clear after loading so it doesn't persist forever
            session()->forget($sessionKey);
        }

        // Fallback: check deployed_tokens from template deployment
        if (! $this->plainToken) {
            $deployedTokens = session('deployed_tokens', []);
            foreach ($deployedTokens as $entry) {
                if ($entry['name'] === $agent->name) {
                    $this->plainToken = $entry['token'];
                    break;
                }
            }
        }
    }

    public function regenerateToken(): void
    {
        $this->plainToken = Str::random(64);
        $this->tokenIsNew = true;
        $this->agent->update([
            'api_token' => hash('sha256', $this->plainToken),
        ]);

        // Note: token is now in Livewire state ($this->plainToken) for the current page view.
        // No need to persist to session since the user is already on the setup page.
    }

    public function setSkillTab(string $tab): void
    {
        $this->skillTab = $tab;
    }

    public function setEnvTab(string $tab): void
    {
        $this->envTab = $tab;
    }

    public function toggleSkill(string $skill): void
    {
        $this->expandedSkill = $this->expandedSkill === $skill ? null : $skill;
    }

    public function toggleFile(string $file): void
    {
        $this->expandedFile = $this->expandedFile === $file ? null : $file;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $apiUrl = rtrim(config('app.url'), '/').'/api/v1';

        $heartbeatInterval = 180;
        $cronExpr = '*/3 * * * *';

        // Determine interval from template or role
        if ($this->agent->is_lead) {
            $heartbeatInterval = 120;
            $cronExpr = '*/2 * * * *';
        } elseif (Str::contains(strtolower($this->agent->role ?? ''), ['monitor', 'scheduler', 'devops', 'ops', 'knowledge'])) {
            $heartbeatInterval = 600;
            $cronExpr = '*/10 * * * *';
        }

        $heartbeatModel = $this->agent->heartbeat_model ?? 'anthropic/claude-haiku-4-5';

        return [
            'apiUrl' => $apiUrl,
            'heartbeatModel' => $heartbeatModel,
            'heartbeatInterval' => $heartbeatInterval,
            'cronExpr' => $cronExpr,
            'agentSlug' => $this->agent->slug,
            'workspacePath' => $this->agent->workspace_path,
            'openclawAgentConfig' => $this->openclawAgentConfig(),
            'openclawFullConfig' => $this->openclawFullConfig(),
            'identityMd' => $this->identityMd(),
            'agentsMd' => $this->agentsMd(),
            'memoryMd' => $this->memoryMd(),
            'userMd' => $this->userMd(),
            'toolsMd' => $this->toolsMd(),
            'createAllFilesScript' => $this->createAllFilesScript(),
            'heartbeatSkillMd' => $this->heartbeatSkillMd($apiUrl),
            'tasksSkillMd' => $this->tasksSkillMd($apiUrl),
            'cronConfigInContext' => $this->cronConfigInContext($cronExpr, $heartbeatModel),
            'cronOnlyConfig' => $this->cronOnlyConfig($cronExpr, $heartbeatModel),
            'curlCommand' => $this->curlCommand($apiUrl),
        ];
    }

    private function openclawAgentConfig(): string
    {
        $config = [
            'name' => $this->agent->slug,
            'model' => $this->agent->work_model ?? 'anthropic/claude-sonnet-4',
            'description' => $this->agent->description ?? $this->agent->role ?? 'Agent',
            'workspace' => $this->agent->workspace_path,
            'tools' => $this->agent->getToolsConfig(),
        ];

        return json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function openclawFullConfig(): string
    {
        $agents = Agent::query()->orderBy('name')->get();

        $agentConfigs = $agents->map(function (Agent $agent) {
            return [
                'name' => $agent->slug,
                'model' => $agent->work_model ?? 'anthropic/claude-sonnet-4',
                'description' => $agent->description ?? $agent->role ?? 'Agent',
                'workspace' => $agent->workspace_path,
                'tools' => $agent->getToolsConfig(),
            ];
        })->values()->toArray();

        return json_encode(['agents' => $agentConfigs], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function identityMd(): string
    {
        $name = $this->agent->name;
        $role = $this->agent->role ?? 'Agent';
        $description = $this->agent->description ?? '';
        $soulExcerpt = '';

        if ($this->agent->soul_md) {
            // Extract working style or first paragraph from soul_md
            $lines = explode("\n", $this->agent->soul_md);
            $excerpt = [];
            foreach ($lines as $line) {
                if (str_starts_with($line, '#')) {
                    continue;
                }
                if (trim($line) !== '') {
                    $excerpt[] = trim($line);
                }
                if (count($excerpt) >= 3) {
                    break;
                }
            }
            $soulExcerpt = implode("\n", $excerpt);
        }

        $content = "# {$name}\n\n";
        $content .= "**Role:** {$role}\n\n";
        if ($description) {
            $content .= "{$description}\n\n";
        }
        if ($soulExcerpt) {
            $content .= "## Working Style\n\n{$soulExcerpt}\n";
        }

        return $content;
    }

    private function agentsMd(): string
    {
        $agents = Agent::query()->orderBy('name')->get();
        $content = "# Team Agents\n\n";
        $content .= "These are the other agents on your team. You can mention them with @name in messages.\n\n";

        foreach ($agents as $agent) {
            $lead = $agent->is_lead ? ' (Lead)' : '';
            $content .= "- **{$agent->name}** (`{$agent->slug}`){$lead} — {$agent->role}\n";
            if ($agent->description) {
                $content .= "  {$agent->description}\n";
            }
        }

        return $content;
    }

    private function memoryMd(): string
    {
        return <<<'MD'
# Memory

This file stores persistent context across sessions. Update it as you learn about the project.

## Project Context

- Project: [Add project name]
- Repository: [Add repo URL]
- Tech stack: [Add key technologies]

## Key Decisions

[Record important decisions made during work]

## Lessons Learned

[Record what worked and what didn't]
MD;
    }

    private function userMd(): string
    {
        $teamName = auth()->user()?->currentTeam?->name ?? 'Your Team';

        return <<<MD
# User Context

## Team

- **Team:** {$teamName}
- **Mission Control:** Connected via API

## Preferences

- Communicate progress via Mission Control messages
- Update task status as you work (in_progress → in_review → done)
- Ask questions in task threads, not standalone messages
MD;
    }

    private function toolsMd(): string
    {
        $skills = $this->agent->skills ?? [];
        $content = "# Available Tools\n\n";
        $content .= "## Mission Control Skills\n\n";
        $content .= "- **mission-control-heartbeat** — Syncs with Mission Control to check in and retrieve work\n";
        $content .= "- **mission-control-tasks** — Manages tasks, messages, artifacts, and threads\n";

        if (! empty($skills)) {
            $content .= "\n## Agent Skills\n\n";
            foreach ($skills as $skill) {
                $content .= "- **{$skill}**\n";
            }
        }

        $content .= "\n## Standard Tools\n\n";
        $content .= "- Bash, Read, Write, Edit, Glob, Grep\n";

        return $content;
    }

    private function createAllFilesScript(): string
    {
        $wp = $this->agent->workspace_path;

        $identity = $this->identityMd();
        $agents = $this->agentsMd();
        $memory = $this->memoryMd();
        $user = $this->userMd();
        $tools = $this->toolsMd();

        return <<<BASH
mkdir -p {$wp}

cat > {$wp}/identity.md << 'IDENTITY_EOF'
{$identity}
IDENTITY_EOF

cat > {$wp}/agents.md << 'AGENTS_EOF'
{$agents}
AGENTS_EOF

cat > {$wp}/memory.md << 'MEMORY_EOF'
{$memory}
MEMORY_EOF

cat > {$wp}/user.md << 'USER_EOF'
{$user}
USER_EOF

cat > {$wp}/tools.md << 'TOOLS_EOF'
{$tools}
TOOLS_EOF

echo "Workspace files created in {$wp}"
BASH;
    }

    private function cronConfigInContext(string $cronExpr, string $heartbeatModel): string
    {
        $config = [
            'name' => $this->agent->slug,
            'model' => $this->agent->work_model ?? 'anthropic/claude-sonnet-4',
            'workspace' => $this->agent->workspace_path,
            'crons' => [
                [
                    'name' => 'Mission Control Heartbeat',
                    'schedule' => [
                        'kind' => 'cron',
                        'expr' => $cronExpr,
                    ],
                    'sessionTarget' => 'isolated',
                    'payload' => [
                        'kind' => 'agentTurn',
                        'model' => $heartbeatModel,
                        'message' => 'Run the mission-control-heartbeat skill now. Sync with Mission Control, report your status, and check for pending work.',
                    ],
                ],
            ],
        ];

        return json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function cronOnlyConfig(string $cronExpr, string $heartbeatModel): string
    {
        $crons = [
            [
                'name' => 'Mission Control Heartbeat',
                'schedule' => [
                    'kind' => 'cron',
                    'expr' => $cronExpr,
                ],
                'sessionTarget' => 'isolated',
                'payload' => [
                    'kind' => 'agentTurn',
                    'model' => $heartbeatModel,
                    'message' => 'Run the mission-control-heartbeat skill now. Sync with Mission Control, report your status, and check for pending work.',
                ],
            ],
        ];

        return json_encode($crons, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function heartbeatSkillMd(string $apiUrl): string
    {
        return <<<'MD'
# Sync with Mission Control

This skill connects to Mission Control to report your current status and retrieve any pending work, notifications, or configuration changes. Run it whenever your cron schedule triggers.

## Environment Variables

- `$MC_API_URL` — Base URL of the Mission Control API (e.g. `https://app.example.com/api/v1`)
- `$MC_AGENT_TOKEN` — Your authentication token (Bearer token)

## Step 1: Check In

Call the sync endpoint to report your status and get work:

```bash
SOUL_HASH=$(shasum -a 256 ~/.openclaw/workspace/SOUL.md 2>/dev/null | cut -d' ' -f1)

curl -s -X POST "$MC_API_URL/heartbeat" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"status\": \"idle\",
    \"soul_hash\": \"$SOUL_HASH\"
  }"
```

### Status Values

Report your current state accurately:
- `idle` — Not currently working on anything (default)
- `busy` — Actively working on a task
- `error` — Something went wrong (include error details)

When reporting an error, add the `error` field:

```bash
curl -s -X POST "$MC_API_URL/heartbeat" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"status\": \"error\",
    \"error\": {
      \"type\": \"build_failure\",
      \"message\": \"npm install failed with exit code 1\",
      \"task_id\": \"$TASK_ID\",
      \"recoverable\": true
    }
  }"
```

## Step 2: Process the Response

The response tells you what to do next. Here is an example:

```json
{
  "status": "ok",
  "notifications": [
    {
      "id": "a1b2c3d4-...",
      "type": "mention",
      "from": "lead-agent",
      "content": "@your-name please review the auth module",
      "thread_id": "e5f6a7b8-...",
      "thread_subject": "Auth module review",
      "linked_task_id": null,
      "thread_context": [
        {
          "sequence": 1,
          "from": "lead-agent",
          "content": "We need someone to review the auth module changes.",
          "created_at": "2025-01-15T10:30:00Z"
        }
      ]
    }
  ],
  "tasks": [
    {
      "id": "f9e8d7c6-...",
      "project": "web-app",
      "title": "Fix login redirect bug",
      "description": "Users are redirected to /dashboard instead of /home after login",
      "status": "assigned",
      "priority": "high",
      "created_by": "b5a4c3d2-...",
      "subtask_of": null,
      "previous_attempts": 0
    }
  ],
  "blocked_summary": {
    "count": 1,
    "next_up": [
      {
        "id": "c3d4e5f6-...",
        "title": "Deploy to staging",
        "waiting_on": ["Fix login redirect bug"]
      }
    ]
  },
  "soul_sync": null,
  "config": {
    "heartbeat_interval_seconds": 180,
    "active_projects": ["web-app"]
  }
}
```

### Processing Rules

Work through these in order:

1. **Check `status`**: If `"paused"`, stop all work immediately. Do not process tasks or notifications. The `reason` field explains why (e.g. `"circuit_breaker"`).

2. **Check `soul_sync`**: If present (not null), your SOUL.md is out of date. Write the `soul_md` content to `~/.openclaw/workspace/SOUL.md` immediately. Save the `soul_hash` so you can send it on next sync.

3. **Check `config`**: If `heartbeat_interval_seconds` differs from your current cron interval, note it — your operator may need to update the cron schedule.

4. **Check `notifications`**: For each notification where `type` is `"mention"`:
   - Read the `thread_context` array to understand the full conversation before responding.
   - Use the mission-control-tasks skill to post a reply to the thread (using `thread_id`).
   - If `linked_task_id` is set, the mention is about a specific task.

5. **Check `tasks`**: If the array is not empty, you have assigned work. Pick the highest-priority task and begin working on it. Use the mission-control-tasks skill to claim the task, update its status, and report results.

6. **Check `blocked_summary`**: This is informational — it shows tasks you own that are waiting on other tasks. No action needed, but it helps you understand your pipeline.

## Error Handling

| Status Code | Meaning | What to Do |
|---|---|---|
| 200 | Success | Process the response as described above |
| 401 | Unauthorized | Your token is invalid or expired. Stop and alert your operator. |
| 422 | Validation error | Your request body is malformed. Check the error message. |
| 429 | Rate limited | Wait and retry on your next scheduled sync. Do not retry immediately. |
| 500 | Server error | Log the error. Retry on your next scheduled sync. |

## Important Notes

- **Never hardcode your token.** Always use the `$MC_AGENT_TOKEN` environment variable.
- **Do not retry on rate limits.** Wait for your next scheduled sync interval.
- **Send `soul_hash` every time.** This avoids unnecessary SOUL.md transfers when content hasn't changed.
- If you are currently working on a task, report `"status": "busy"` and include `"current_task_id"` in the request body.
MD;
    }

    private function tasksSkillMd(string $apiUrl): string
    {
        return <<<'MD'
# Mission Control Tasks & Communication

This skill covers all task management, messaging, and artifact operations through the Mission Control API.

## Environment Variables

- `$MC_API_URL` — Base URL of the Mission Control API (e.g. `https://app.example.com/api/v1`)
- `$MC_AGENT_TOKEN` — Your authentication token (Bearer token)

---

## Tasks

### List Tasks

Fetch tasks assigned to you or browse available work:

```bash
# Get tasks assigned to me
curl -s "$MC_API_URL/tasks?assigned_to=me" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Accept: application/json"

# Filter by project and status
curl -s "$MC_API_URL/tasks?project_id=$PROJECT_ID&status=backlog" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Accept: application/json"
```

Available status filters: `backlog`, `assigned`, `in_progress`, `in_review`, `done`, `cancelled`

Example response:

```json
{
  "data": [
    {
      "id": "f9e8d7c6-...",
      "project_id": "a1b2c3d4-...",
      "title": "Fix login redirect bug",
      "description": "Users are redirected to /dashboard instead of /home",
      "status": "assigned",
      "priority": "high",
      "assigned_agent_id": "your-agent-id",
      "parent_task_id": null,
      "tags": ["bug", "auth"],
      "result": null,
      "created_at": "2025-01-15T10:00:00Z"
    }
  ]
}
```

### Claim a Task

Before working on a task, claim it so other agents know it's taken:

```bash
curl -s -X POST "$MC_API_URL/tasks/$TASK_ID/claim" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Accept: application/json"
```

This assigns the task to you and sets `claimed_at`. Returns the updated task.

**If you get a 409 response**, the task was already claimed by another agent. Do not retry — fetch the task list again and pick a different one.

```json
{
  "message": "Task is not available for claiming."
}
```

### Create a Task

Create new tasks or subtasks:

```bash
curl -s -X POST "$MC_API_URL/tasks" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "title": "Implement user avatar upload",
    "description": "Add avatar upload to the user profile page with image validation",
    "project_id": "'"$PROJECT_ID"'",
    "priority": "medium",
    "tags": ["feature", "profile"]
  }'
```

#### Creating a Subtask

Set `parent_task_id` to nest under a parent. Maximum depth is 2 levels.

```bash
curl -s -X POST "$MC_API_URL/tasks" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "title": "Add image validation for avatars",
    "project_id": "'"$PROJECT_ID"'",
    "parent_task_id": "'"$PARENT_TASK_ID"'"
  }'
```

#### Creating with Dependencies

Use `depends_on` to mark tasks that must complete first. The new task will start in `blocked` status until all dependencies are `done`.

```bash
curl -s -X POST "$MC_API_URL/tasks" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "title": "Deploy avatar feature to staging",
    "project_id": "'"$PROJECT_ID"'",
    "depends_on": ["'"$DEP_TASK_1"'", "'"$DEP_TASK_2"'"]
  }'
```

#### Assigning to Another Agent

Use `assigned_agent_name` (the agent's name, not UUID):

```bash
curl -s -X POST "$MC_API_URL/tasks" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "title": "Review avatar upload code",
    "project_id": "'"$PROJECT_ID"'",
    "assigned_agent_name": "reviewer-agent"
  }'
```

#### Including an Initial Message

Add `initial_message` to automatically create a discussion thread on the task:

```bash
curl -s -X POST "$MC_API_URL/tasks" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "title": "Investigate memory leak in worker",
    "project_id": "'"$PROJECT_ID"'",
    "initial_message": "I noticed the worker process grows to 2GB after 4 hours. Starting investigation."
  }'
```

### Update Task Status

Progress through the task lifecycle:

```bash
# Start working
curl -s -X PUT "$MC_API_URL/tasks/$TASK_ID" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"status": "in_progress"}'

# Submit for review
curl -s -X PUT "$MC_API_URL/tasks/$TASK_ID" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"status": "in_review"}'

# Complete with results
curl -s -X PUT "$MC_API_URL/tasks/$TASK_ID" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "status": "done",
    "result": "Fixed the login redirect. Changed /dashboard to /home in AuthController@login. Added test in LoginRedirectTest."
  }'

# Cancel a task
curl -s -X PUT "$MC_API_URL/tasks/$TASK_ID" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "status": "cancelled",
    "result": "Duplicate of task f9e8d7c6. Closing."
  }'
```

Valid status transitions:
- `assigned` → `in_progress`
- `in_progress` → `in_review`, `done`, `cancelled`
- `in_review` → `in_progress`, `done`, `cancelled`

You cannot transition from `blocked`, `backlog`, `done`, or `cancelled` — those are system-managed.

---

## Messages & Threads

### Start a New Thread

Create a message without a `thread_id` to start a new conversation. Include a `subject` for the thread.

```bash
curl -s -X POST "$MC_API_URL/messages" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "content": "I have finished the auth module refactor. @reviewer-agent can you take a look?",
    "project_id": "'"$PROJECT_ID"'",
    "subject": "Auth module refactor complete",
    "message_type": "review_request"
  }'
```

Example response:

```json
{
  "data": {
    "id": "m1n2o3p4-...",
    "thread_id": "t5u6v7w8-...",
    "sequence_in_thread": 1,
    "content": "I have finished the auth module refactor. @reviewer-agent can you take a look?",
    "mentions": ["reviewer-agent-uuid"],
    "message_type": "review_request",
    "thread": {
      "id": "t5u6v7w8-...",
      "subject": "Auth module refactor complete",
      "task_id": null
    }
  }
}
```

### Reply to a Thread

Include `thread_id` to post in an existing conversation:

```bash
curl -s -X POST "$MC_API_URL/messages" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "content": "Looks good! One suggestion: add rate limiting to the login endpoint.",
    "thread_id": "'"$THREAD_ID"'"
  }'
```

### Mention Another Agent

Use `@agent-name` in message content. Mission Control will parse mentions and notify the mentioned agents on their next sync.

### Read Messages

```bash
# Get all messages in a thread
curl -s "$MC_API_URL/messages?thread_id=$THREAD_ID" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Accept: application/json"

# Get messages mentioning me
curl -s "$MC_API_URL/messages?mentioning=me" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Accept: application/json"

# Get messages in a project
curl -s "$MC_API_URL/messages?project_id=$PROJECT_ID" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Accept: application/json"
```

### Message Types

Use `message_type` to categorize messages:
- `chat` — General conversation (default)
- `status_update` — Progress update on work
- `task_update` — Task-related notification
- `standup` — Daily standup message
- `review_request` — Requesting code or work review

### Resolve a Thread

Mark a thread as resolved when the conversation is complete:

```bash
curl -s -X PATCH "$MC_API_URL/threads/$THREAD_ID" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"is_resolved": true}'
```

---

## Artifacts

Attach files and documents to tasks.

### Upload Inline (Text Content)

For text files under 50KB, send content directly:

```bash
curl -s -X POST "$MC_API_URL/tasks/$TASK_ID/artifacts" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "filename": "analysis-report.md",
    "display_name": "Performance Analysis Report",
    "mime_type": "text/markdown",
    "artifact_type": "document",
    "content": "# Performance Report\n\n## Summary\nResponse times improved by 40% after query optimization..."
  }'
```

### Upload via Presigned URL (Large Files)

For larger files, use the two-step presigned upload:

**Step 1: Create the artifact record**

```bash
curl -s -X POST "$MC_API_URL/tasks/$TASK_ID/artifacts" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "filename": "screenshot.png",
    "mime_type": "image/png",
    "artifact_type": "image"
  }'
```

If the server uses S3, the response includes `upload_url` and `upload_method`:

```json
{
  "data": { "id": "art-uuid-...", "confirmed_at": null, ... },
  "upload_url": "https://s3.amazonaws.com/bucket/path?X-Amz-Signature=...",
  "upload_method": "PUT"
}
```

**Step 2: Upload the file to the presigned URL**

```bash
curl -s -X PUT "$UPLOAD_URL" \
  -H "Content-Type: image/png" \
  --data-binary @screenshot.png
```

**Step 3: Confirm the upload**

```bash
curl -s -X POST "$MC_API_URL/artifacts/$ARTIFACT_ID/confirm" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Accept: application/json"
```

If confirmation fails with 422 ("File not found on storage"), the upload to the presigned URL did not complete. Retry step 2.

### Upload via Multipart Form

Alternatively, upload files directly:

```bash
curl -s -X POST "$MC_API_URL/artifacts/$ARTIFACT_ID/upload" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -F "file=@screenshot.png"
```

This uploads, confirms, and returns the artifact in one step. Max file size: 100MB.

### List Artifacts

```bash
curl -s "$MC_API_URL/tasks/$TASK_ID/artifacts" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Accept: application/json"
```

For text artifacts, the response includes `content_inline` with the file contents. For S3-stored files, a temporary `download_url` is provided.

---

## Projects

### List Projects

```bash
curl -s "$MC_API_URL/projects" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Accept: application/json"
```

Example response:

```json
{
  "data": [
    {
      "id": "a1b2c3d4-...",
      "name": "web-app",
      "description": "Main web application",
      "status": "active"
    }
  ]
}
```

---

## SOUL.md

### Fetch Your SOUL.md

```bash
curl -s "$MC_API_URL/soul" \
  -H "Authorization: Bearer $MC_AGENT_TOKEN" \
  -H "Accept: application/json"
```

Response:

```json
{
  "data": {
    "soul_md": "# Agent Identity\n\nYou are...",
    "soul_hash": "a1b2c3d4e5f6..."
  }
}
```

---

## Error Reference

| Code | Meaning | Action |
|---|---|---|
| 401 | Unauthorized | Token is invalid or expired. Stop and alert your operator. |
| 404 | Not Found | The resource (task, thread, artifact) does not exist or is not in your team. |
| 409 | Conflict | Resource state conflict (task already claimed, artifact already confirmed). Do not retry — fetch fresh data and try a different action. |
| 422 | Validation Error | Request body failed validation. Read the `message` field for details. Common causes: invalid status transition, missing required fields, exceeding max depth. |
| 429 | Rate Limited | Too many requests. Back off and retry after a delay. |
| 500 | Server Error | Something went wrong on the server. Log it and retry later. |

## Important Notes

- **Never hardcode your token.** Always use `$MC_AGENT_TOKEN`.
- **Always include `Accept: application/json`** to get JSON responses.
- **Task IDs are UUIDs.** They look like `f9e8d7c6-b5a4-3d2e-1f0a-9b8c7d6e5f4a`.
- **@mentions are parsed from message content.** Write `@agent-name` naturally in your message text.
- When a 409 occurs on task claim, **do not retry the same task** — another agent got it first. Fetch the list again and pick a different one.
MD;
    }

    private function curlCommand(string $apiUrl): string
    {
        $token = $this->plainToken ?? 'YOUR_TOKEN_HERE';

        return <<<BASH
curl -s -X POST "{$apiUrl}/heartbeat" \\
  -H "Authorization: Bearer {$token}" \\
  -H "Content-Type: application/json" \\
  -H "Accept: application/json" \\
  -d '{"status": "idle"}' | python3 -m json.tool
BASH;
    }

    public function getTitle(): string
    {
        return "Setup: {$this->agent->name}";
    }
}
