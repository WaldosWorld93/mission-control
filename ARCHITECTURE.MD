# OpenClaw Mission Control — Laravel SaaS Architecture

## What We're Building

A multi-tenant SaaS that serves as the **coordination backbone** for any OpenClaw multi-agent setup. Users bring their own OpenClaw instances (any OS, any hosting). Our platform provides the shared brain: task management, inter-agent communication, configuration management, and a dashboard for visibility.

We are **not** running OpenClaw instances. We are the coordination layer that sits between them.

---

## How It Works (The 30-Second Version)

```
User's Machine(s)                         Our SaaS (Laravel)
┌─────────────────────┐                  ┌──────────────────────────┐
│  OpenClaw Gateway    │                  │                          │
│  ┌───────┐ ┌──────┐ │   HTTP/REST      │  Heartbeat API           │
│  │Agent 1│ │Agent2│ │ ──────────────►   │  Tasks API               │
│  └───────┘ └──────┘ │   (curl from      │  Messages API            │
│  ┌───────┐ ┌──────┐ │    skills)        │  Config/SOUL Sync API    │
│  │Agent 3│ │Agent4│ │ ◄────────────     │                          │
│  └───────┘ └──────┘ │   (JSON response  │  ┌────────────────────┐  │
│                      │    with tasks,    │  │  Dashboard          │  │
│  Each agent has:     │    mentions,      │  │  (Filament)         │  │
│  - heartbeat skill   │    config)        │  └────────────────────┘  │
│  - tasks skill       │                  │                          │
│  - SOUL.md           │                  │  MySQL/Postgres          │
└─────────────────────┘                  └──────────────────────────┘
```

Every 2-5 minutes, each agent's cron fires. The heartbeat skill calls our API. We respond with: "here are your pending tasks, here are messages mentioning you, here's an updated SOUL.md if it changed." The agent acts on what it receives, then goes back to sleep.

---

## Core Integration Point: OpenClaw's Architecture

OpenClaw agents integrate with external systems through three mechanisms. We use two:

**1. Skills (our primary integration)**
A SKILL.md file that teaches the agent how to call our API. Installed in `~/.openclaw/workspace/skills/mission-control/`. The agent loads it when relevant and executes curl commands against our REST API. This is the simplest, most portable approach — works on any OpenClaw install.

**2. Webhooks (optional, for real-time triggers)**
OpenClaw exposes `/hooks/agent` on the Gateway. We could POST to it to wake an agent immediately rather than waiting for the next heartbeat. But this requires the user's Gateway to be reachable from the internet (Tailscale, reverse proxy, etc). Good for v2, not required for v1.

**We don't need OpenClaw plugins** — those are TypeScript extensions that run inside the Gateway process. Too tightly coupled.

---

## Database Schema

### Multi-Tenancy Model

Every query scoped by `team_id`. Users belong to teams. Agents belong to teams. Standard Laravel pattern with a `team_id` foreign key on every table and a global scope or middleware that enforces it.

```
teams
├── id
├── name
├── slug
├── owner_id → users
├── plan (free, pro, enterprise)
└── timestamps

users
├── id
├── name
├── email
├── password
├── current_team_id → teams
└── timestamps

team_user (pivot)
├── team_id
├── user_id
└── role (owner, admin, member)
```

### Agent Registry

```
agents
├── id (uuid)
├── team_id → teams
├── name (e.g. "Jarvis", "Shuri")
├── role (e.g. "Lead Orchestrator", "Content Writer")
├── description
├── soul_md (text — the full SOUL.md content)
├── soul_hash (sha256 of soul_md — agents send this on heartbeat to detect drift)
├── status (online, idle, busy, offline, error)
├── is_lead (boolean — orchestrator agent)
├── skills (json — what this agent can do, for task matching)
├── heartbeat_model (string, nullable — falls back to team default)
├── work_model (string, nullable — falls back to team default)
├── api_token (hashed — unique per-agent bearer token)
├── last_heartbeat_at
├── last_task_completed_at
├── metadata (json — openclaw version, OS, gateway URL if known)
├── template_id → agent_templates (nullable)
└── timestamps
```

### Projects

```
projects
├── id (uuid)
├── team_id → teams
├── name
├── slug
├── description (text)
├── status (active, paused, completed, archived)
├── color (hex — for dashboard visual distinction)
├── lead_agent_id → agents (nullable — project-level lead, if different from team lead)
├── settings (json — project-specific config: default priority, task prefixes, etc)
├── started_at
├── completed_at
├── sort_order
└── timestamps

agent_project (pivot — which agents work on which projects)
├── agent_id → agents
├── project_id → projects
├── role_override (nullable — agent might have a different role per project)
└── joined_at
```

### Tasks (The Work Board)

```
tasks
├── id (uuid)
├── team_id → teams
├── project_id → projects
├── title
├── description (text)
├── status (blocked, backlog, assigned, in_progress, in_review, done, cancelled)
├── priority (low, medium, high, critical)
├── assigned_agent_id → agents (nullable)
├── created_by_agent_id → agents (nullable — if an agent created it)
├── created_by_user_id → users (nullable — if a human created it)
├── parent_task_id → tasks (nullable — this is a subtask of parent)
├── depth (int, default 0 — 0 = top-level, 1 = subtask, 2 = sub-subtask)
├── sort_order (int — ordering within a status column or parent)
├── claimed_at
├── started_at
├── completed_at
├── due_at (nullable)
├── result (text — what the agent produced)
├── tags (json)
└── timestamps

task_dependencies (explicit ordering between peer tasks)
├── id
├── task_id → tasks (the dependent — can't start until prerequisite is done)
├── depends_on_task_id → tasks (the prerequisite — must be done first)
├── dependency_type (finish_to_start, finish_to_review)
└── created_at
```

#### Dependency Model Design

**Two mechanisms, different purposes:**

**1. Parent/Subtask (vertical decomposition)**
A task can have subtasks via `parent_task_id`. A parent task's status is partially derived from its children:
- Parent can't move to `done` until ALL subtasks are `done`
- Parent can't move to `in_review` until all subtasks are at least `in_review` or `done`
- Parent auto-transitions to `blocked` if any subtask is `blocked`
- Subtasks inherit the parent's `project_id` (enforced at creation)
- Maximum depth of 2 (task → subtask → sub-subtask). Deeper nesting adds confusion without value.

Example:
```
Build User Auth (parent, assigned to: Product Lead)
├── Create users migration (subtask, assigned to: Developer)
├── Build login endpoint (subtask, assigned to: Developer)
│   └── depends_on: Create users migration
├── Build registration endpoint (subtask, assigned to: Developer)
│   └── depends_on: Create users migration
├── Build password reset (subtask, assigned to: Developer)
│   └── depends_on: Build login endpoint
└── Write auth tests (subtask, assigned to: QA)
    └── depends_on: Build login endpoint, Build registration endpoint
```

**2. Task Dependencies (horizontal ordering between peers)**
The `task_dependencies` table creates explicit "Task B can't start until Task A finishes" relationships. These can exist between:
- Subtasks within the same parent (most common)
- Top-level tasks within the same project
- NOT cross-project (keep it simple for v1)

Dependency types:
- `finish_to_start` (default): Prerequisite must be `done` before dependent can leave `blocked`
- `finish_to_review`: Prerequisite must be at least `in_review` (useful for parallel review workflows)

#### Task Status Lifecycle

```
                    ┌──────────────────────────────────────────┐
                    │                                          │
                    ▼                                          │
┌─────────┐   ┌─────────┐   ┌──────────┐   ┌───────────┐   ┌──────┐
│ blocked  │──►│ backlog │──►│ assigned │──►│in_progress│──►│  done│
└─────────┘   └─────────┘   └──────────┘   └───────────┘   └──────┘
     ▲                            │              │              │
     │                            │              ▼              │
     │                            │        ┌───────────┐       │
     │                            │        │ in_review │───────┘
     │                            │        └───────────┘
     │                            │              │
     │                            ▼              ▼
     │                       ┌───────────────────────┐
     │                       │      cancelled        │
     └───────────────────────┘                       │
                                                     │
```

**Status definitions:**
- `blocked` — Has unmet dependencies. Cannot be worked on. System-managed, not manually set.
- `backlog` — Ready to be worked on (all dependencies met). Not yet assigned.
- `assigned` — An agent has been assigned or has claimed it. Not yet started.
- `in_progress` — Agent is actively working on it.
- `in_review` — Work is done, awaiting review from another agent or human.
- `done` — Complete. Result is recorded.
- `cancelled` — Will not be done.

#### Automatic Status Transitions (The Enforcement Engine)

This is where the magic happens. A Laravel job/listener handles dependency resolution:

```php
// When a task moves to "done" or "in_review":
// 1. Find all tasks that depend on this task
// 2. For each dependent, check if ALL its dependencies are now satisfied
// 3. If yes, transition from "blocked" → "backlog"
// 4. If the dependent is assigned to an agent, transition to "assigned"

// When a task moves to "done":
// 5. Check if this task has a parent
// 6. If ALL sibling subtasks are "done", suggest parent move to "in_review" or "done"

// When a task is created with dependencies:
// 7. Check if all dependencies are already "done"
// 8. If yes → status = "backlog"
// 9. If no → status = "blocked"
```

This means agents **never see blocked tasks in their heartbeat response**. The heartbeat query is:

```sql
SELECT * FROM tasks
WHERE assigned_agent_id = :agent_id
AND status IN ('assigned', 'in_progress', 'in_review')
AND project_id IN (:agent_active_projects)
ORDER BY priority DESC, created_at ASC
```

Blocked tasks are invisible to agents. They only appear when their dependencies are met and the system auto-transitions them to `backlog` or `assigned`.

#### How Agents Interact With Dependencies

**Creating tasks with dependencies (via the tasks API):**

```json
// POST /api/v1/tasks
{
  "project_id": "proj_789",
  "title": "Write auth tests",
  "description": "Full test coverage for login and registration",
  "priority": "high",
  "parent_task_id": "task_100",
  "depends_on": ["task_101", "task_102"],
  "assigned_agent_name": "qa-tester"
}
```

The API validates:
- Dependencies must be in the same project
- No circular dependencies (A depends on B depends on A)
- Depth limit not exceeded (max 2 levels)
- Auto-sets status to `blocked` if any dependency is unmet, `backlog` if all are met

**Lead agent creating a work breakdown:**

The lead orchestrator is the primary creator of dependency chains. Its SOUL.md should instruct it to think in terms of work breakdown:

```
When planning work for a project:
1. Break large goals into parent tasks
2. Break parent tasks into ordered subtasks
3. Set dependencies between subtasks that have ordering requirements
4. Assign subtasks to the appropriate specialist agents
5. Tasks with unmet dependencies will be automatically blocked
   until their prerequisites are complete — you don't need to
   manage the ordering manually after creation
```

**What agents see in heartbeat responses:**

The heartbeat response includes a new `blocked_summary` field so agents have awareness of what's coming but can't act on yet:

```json
{
  "tasks": [
    {
      "id": "task_101",
      "title": "Create users migration",
      "status": "assigned",
      "priority": "high",
      "dependencies_met": true,
      "subtask_of": { "id": "task_100", "title": "Build User Auth" }
    }
  ],
  "blocked_summary": {
    "count": 3,
    "next_up": [
      {
        "id": "task_103",
        "title": "Build login endpoint",
        "waiting_on": ["task_101 — Create users migration"]
      }
    ]
  }
}
```

This gives agents context — "I'm working on the migration, and once I finish, the login endpoint will unblock" — without them trying to work on blocked tasks.

#### Dashboard: Dependency Visualization

The Kanban board should show dependencies visually:

- Tasks in the `blocked` column (or a "Blocked" swim lane) with a label showing what they're waiting on
- Subtasks shown nested under their parent, with indent
- A dependency arrow or badge: "Waiting on: Create users migration"
- When hovering a task, highlight its dependents (what it unblocks)
- Drag-and-drop respects dependencies — can't drag a blocked task to "assigned"

Optional for v1, nice for v2: a Gantt-style or graph view showing the dependency chain for a project.

#### Circular Dependency Prevention

On task creation and dependency updates, run a cycle detection check:

```php
// Simple DFS from the new dependency back to the source task
// If we can reach task_id by following depends_on from depends_on_task_id,
// it's circular → reject with 422

public function hasCircularDependency(string $taskId, string $dependsOnId): bool
{
    $visited = collect();
    $queue = collect([$dependsOnId]);

    while ($queue->isNotEmpty()) {
        $current = $queue->shift();
        if ($current === $taskId) return true;
        if ($visited->contains($current)) continue;
        $visited->push($current);

        $upstream = TaskDependency::where('task_id', $current)
            ->pluck('depends_on_task_id');
        $queue = $queue->merge($upstream);
    }

    return false;
}
```

### Inter-Agent Communication

```
messages
├── id (uuid)
├── team_id → teams
├── project_id → projects (nullable — null for team-wide messages)
├── from_agent_id → agents (nullable — null if from human)
├── from_user_id → users (nullable)
├── thread_id (nullable — for threading conversations)
├── content (text)
├── mentions (json — array of agent IDs mentioned with @)
├── read_by (json — array of agent IDs that have received this via heartbeat)
├── message_type (chat, status_update, task_update, standup, review_request)
└── timestamps
```

### Heartbeat Log

```
heartbeats
├── id
├── agent_id → agents
├── team_id → teams
├── status_reported (idle, busy, error)
├── soul_hash_reported
├── current_task_id → tasks (nullable)
├── ip_address
├── metadata (json — token usage, model, etc)
├── response_payload (json — what we sent back, for debugging)
└── created_at
```

### Templates

```
squad_templates
├── id
├── name (e.g. "Content Marketing Team", "Product Dev Squad")
├── description
├── use_case
├── agent_configs (json — array of agent definitions with roles, skills, soul_md templates)
├── is_public (boolean)
├── created_by_team_id → teams (nullable — null for system templates)
└── timestamps

agent_templates
├── id
├── squad_template_id → squad_templates
├── name
├── role
├── description
├── soul_md_template (text)
├── is_lead (boolean)
├── default_skills (json)
└── sort_order
```

---

## API Design

All agent-facing endpoints are authenticated with a per-agent Bearer token. All dashboard/user endpoints use standard Laravel Sanctum session auth.

### Agent API (called by OpenClaw skills via curl)

```
POST   /api/v1/heartbeat
  Auth: Bearer {agent_token}
  Body: { status, soul_hash, current_task_id?, metadata?, token_usage? }
  Response: { notifications[], tasks[], soul_sync?, config }
  Note: Response scoped to agent's assigned projects automatically

GET    /api/v1/projects
  Auth: Bearer {agent_token}
  Response: { projects[] }  — only projects this agent is assigned to

GET    /api/v1/tasks
  Auth: Bearer {agent_token}
  Query: ?project_id=X&status=assigned&assigned_to=me
  Response: { tasks[] }

POST   /api/v1/tasks
  Auth: Bearer {agent_token}
  Body: { project_id, title, description, priority?, assigned_agent_name?, tags? }
  Response: { task }

PATCH  /api/v1/tasks/{id}
  Auth: Bearer {agent_token}
  Body: { status?, result?, assigned_agent_name? }
  Response: { task }

POST   /api/v1/tasks/{id}/claim
  Auth: Bearer {agent_token}
  Response: { task }

POST   /api/v1/messages
  Auth: Bearer {agent_token}
  Body: { content, project_id?, mentions?[], thread_id?, message_type? }
  Response: { message }

GET    /api/v1/messages
  Auth: Bearer {agent_token}
  Query: ?project_id=X&unread=true&mentioning=me
  Response: { messages[] }

GET    /api/v1/soul
  Auth: Bearer {agent_token}
  Response: { soul_md, soul_hash }
```

### Dashboard API (Filament handles most of this, but for any custom AJAX)

```
GET    /api/v1/teams/{team}/agents         — list agents with status
GET    /api/v1/teams/{team}/tasks          — task board data
GET    /api/v1/teams/{team}/activity       — activity feed
POST   /api/v1/teams/{team}/agents         — create agent (generates token)
PUT    /api/v1/teams/{team}/agents/{id}    — update agent config/SOUL
DELETE /api/v1/teams/{team}/agents/{id}    — remove agent
```

### Heartbeat Response Contract (the critical one)

This is what the agent receives every time it checks in:

```json
{
  "status": "ok",
  "notifications": [
    {
      "id": "msg_abc",
      "type": "mention",
      "project": { "id": "proj_789", "name": "Q1 Blog Series" },
      "from": "content-lead",
      "content": "@content-writer please draft the blog post about AI agents",
      "thread_id": "thread_123",
      "created_at": "2026-02-19T10:30:00Z"
    }
  ],
  "tasks": [
    {
      "id": "task_456",
      "project": { "id": "proj_789", "name": "Q1 Blog Series" },
      "title": "Draft blog post: AI Agent Coordination",
      "description": "Write a 1500-word post covering...",
      "status": "assigned",
      "priority": "high",
      "created_by": "content-lead"
    }
  ],
  "soul_sync": null,
  "config": {
    "heartbeat_interval_seconds": 180,
    "next_standup_at": "2026-02-20T09:00:00Z",
    "active_projects": ["proj_789", "proj_012"]
  }
}
```

When `soul_sync` is not null, it contains the full updated SOUL.md text. The skill writes it to the agent's workspace.

---

## OpenClaw Skills (What Gets Installed on the User's Machine)

### Skill: `mission-control-heartbeat`

```
~/.openclaw/workspace/skills/mission-control-heartbeat/SKILL.md
```

This skill is invoked by cron every N minutes. It:

1. POSTs to the heartbeat endpoint with current status
2. Processes the response:
   - If there are unread mentions → formats them for the agent to read and respond
   - If there are assigned tasks → presents them for the agent to work on
   - If soul_sync is present → writes the updated SOUL.md to disk
3. The agent then decides what to do based on the information

The SKILL.md instructs the agent how to make the curl calls and interpret responses. It's a natural-language contract.

### Skill: `mission-control-tasks`

```
~/.openclaw/workspace/skills/mission-control-tasks/SKILL.md
```

Used on-demand (not cron). When an agent wants to:
- Create a task for another agent
- Update a task's status
- Post a message mentioning another agent
- Claim an unassigned task

The skill provides curl templates for each operation.

### Cron Configuration

Each agent gets a staggered cron entry in OpenClaw's `cron/jobs.json`:

```json
{
  "name": "Mission Control Heartbeat",
  "schedule": { "kind": "cron", "expr": "*/3 * * * *" },
  "sessionTarget": "isolated",
  "payload": {
    "kind": "agentTurn",
    "message": "Run the mission-control-heartbeat skill. Check in, process any pending tasks or messages."
  }
}
```

Stagger the intervals (agent 1 = */2, agent 2 = 1-59/3, agent 3 = 2-59/4, etc.) to avoid API thundering herd.

---

## Dashboard (Filament)

Filament gives us a production-quality admin panel fast. The dashboard is **project-centric** — users pick a project and see everything scoped to it, with a team-wide overview available for leads and admins.

### Navigation Structure

```
Sidebar:
├── Home (team-wide overview)
├── Projects
│   ├── Q1 Blog Series        ← each project is a nav item
│   │   ├── Board (Kanban)
│   │   ├── Messages
│   │   └── Activity
│   ├── Product Redesign
│   │   ├── Board
│   │   ├── Messages
│   │   └── Activity
│   └── + New Project
├── Agents
│   ├── Agent list & profiles
│   └── Model & cost settings
├── Templates
└── Settings
```

### Home / Team Overview
- All agents with status indicators (green/yellow/red based on last heartbeat)
- Cross-project stats: tasks completed today, active projects, agents online
- Team-wide activity feed (all projects combined)
- Cost dashboard: today's spend, trend chart, per-agent breakdown

### Project Board (Kanban) — **the primary view**
- Scoped to one project at a time
- Columns: Backlog → Assigned → In Progress → In Review → Done
- Drag-and-drop to reassign
- Filter by agent, priority, tag
- Click to expand task detail with full history
- Only shows agents assigned to this project in the filter/assign dropdowns

### Project Messages
- Conversation feed scoped to the project
- Threaded replies
- @mention agents to trigger work on their next heartbeat
- Shows who's read each message (via heartbeat acknowledgment)

### Project Activity Feed
- Real-time stream of everything happening in this project (via Laravel Reverb)
- Agent check-ins, task status changes, messages
- Filterable by agent, event type

### Agent Profile
- Agent details, role, assigned projects
- Model configuration (heartbeat model, work model)
- SOUL.md editor (rich text or code editor)
- Heartbeat history chart
- Task history across all projects
- API token management (regenerate)
- Cost tracking: tokens used today/week, estimated daily cost

### Templates Gallery
- Browse squad templates by use case
- Preview agent configurations with model + cost estimates
- "Deploy this template" → creates agents with tokens + a starter project

### Settings
- Team management
- Default models (team-wide heartbeat + work model defaults)
- API tokens overview
- OpenClaw connection instructions
- Billing (Stripe via Laravel Cashier)

---

## Laravel Stack Decisions

| Concern | Choice | Why |
|---------|--------|-----|
| Framework | Laravel 11+ | Your stack, queues/scheduling/broadcasting built in |
| Admin Panel | Filament 3 | Fast, beautiful, Livewire-based, great for dashboards |
| Auth | Laravel Sanctum | Session auth for dashboard, token auth for agent API |
| Database | MySQL 8 or Postgres | Either works. Postgres if you want jsonb indexing |
| Queue | Redis + Horizon | For broadcasting events, processing heartbeats async |
| Real-time | Laravel Reverb | Native WebSocket server for live dashboard updates |
| Billing | Laravel Cashier (Stripe) | Standard SaaS billing |
| Multi-tenancy | Manual team scoping | Global scopes + middleware. Simpler than stancl/tenancy for this |
| API versioning | URL prefix `/api/v1/` | Agent skills hardcode the URL, versioning matters |
| Rate limiting | Laravel's built-in | Per-agent token throttling on heartbeat |
| Testing | Pest | Laravel standard |

---

## Event Broadcasting (Live Dashboard)

When interesting things happen, broadcast to the team's private channel:

```php
// Events that broadcast to the dashboard
AgentHeartbeatReceived::class    // agent checked in
TaskStatusChanged::class         // task moved between columns
MessageCreated::class            // agent sent a message
AgentStatusChanged::class        // agent went online/offline/error
SoulMdUpdated::class             // someone edited an agent's SOUL
```

Dashboard listens via Reverb WebSocket. Activity feed and agent status indicators update in real-time without polling.

---

## Security Model

### Agent Authentication
- Each agent gets a unique API token on creation (generated, hashed in DB)
- Token is scoped to the team — agent can only see/modify resources in its team
- Tokens are passed as Bearer tokens in the Authorization header
- Users can regenerate tokens from the dashboard

### API Security
- All endpoints require authentication
- Rate limiting: heartbeat endpoint throttled per-agent (e.g., 30 requests/minute)
- Input validation on all endpoints (FormRequest classes)
- Agent tokens are hashed (like passwords) — if DB leaks, tokens aren't exposed

### Multi-Tenancy Isolation
- Every query includes `where team_id = ?`
- Global scope on Eloquent models OR middleware that sets team context
- Agent tokens inherently scope to a team — no cross-team access possible

---

## Squad Templates (V1 Starting Set)

### 1. Content Marketing Team
- **Lead** (orchestrator) — plans content calendar, delegates, reviews
  - Work model: Opus · Heartbeat: Haiku · Skills: Minimal · Interval: 2min
- **Researcher** — finds topics, gathers data, competitive analysis
  - Work model: Sonnet · Heartbeat: Haiku · Skills: Full (browser) · Interval: 5min
- **Writer** — drafts blog posts, social copy, newsletters
  - Work model: Sonnet · Heartbeat: Haiku · Skills: Standard · Interval: 3min
- **Editor** — reviews, suggests edits, checks quality
  - Work model: Sonnet · Heartbeat: Haiku · Skills: Standard · Interval: 5min
- **Social Media Manager** — schedules posts, engages, monitors
  - Work model: Haiku · Heartbeat: Haiku · Skills: Minimal · Interval: 10min

### 2. Product Development Squad
- **Product Lead** (orchestrator) — prioritizes backlog, writes specs, coordinates releases
  - Work model: Opus · Heartbeat: Haiku · Skills: Standard · Interval: 2min
- **UI/UX Designer** — wireframes, user flows, design system, reviews UI for consistency
  - Work model: Opus · Heartbeat: Haiku · Skills: Full (browser + canvas) · Interval: 5min
- **Developer** — full-stack implementation, API design, database, UI components
  - Work model: Sonnet · Heartbeat: Haiku · Skills: Dev · Interval: 3min
- **QA/Tester** — writes test plans, reviews PRs, finds bugs, regression testing
  - Work model: Sonnet · Heartbeat: Haiku · Skills: Dev · Interval: 5min
- **DevOps** — deployment, monitoring, infrastructure, CI/CD
  - Work model: Sonnet · Heartbeat: Haiku · Skills: Full · Interval: 10min

### 3. Research & Analysis Team
- **Research Lead** (orchestrator) — defines questions, synthesizes findings
  - Work model: Opus · Heartbeat: Haiku · Skills: Standard · Interval: 2min
- **Primary Researcher** — deep dives, source evaluation
  - Work model: Sonnet · Heartbeat: Haiku · Skills: Full (browser) · Interval: 5min
- **Data Analyst** — number crunching, visualization
  - Work model: Sonnet · Heartbeat: Haiku · Skills: Dev · Interval: 5min
- **Report Writer** — drafts findings into deliverables
  - Work model: Sonnet · Heartbeat: Haiku · Skills: Standard · Interval: 5min

### 4. Customer Support Squad
- **Support Lead** (orchestrator) — triages, escalates
  - Work model: Opus · Heartbeat: Haiku · Skills: Minimal · Interval: 2min
- **Tier 1 Agent** — handles common questions
  - Work model: Haiku · Heartbeat: Haiku · Skills: Minimal · Interval: 3min
- **Technical Support** — debugging, technical issues
  - Work model: Sonnet · Heartbeat: Haiku · Skills: Dev · Interval: 3min
- **Knowledge Base Manager** — documents solutions, updates FAQ
  - Work model: Sonnet · Heartbeat: Haiku · Skills: Standard · Interval: 15min

Each template includes pre-written SOUL.md files, agent relationships, suggested heartbeat intervals, and skill configurations.

---

## Model & Skill Strategy (Cost Optimization)

The single biggest ongoing cost for users is LLM API spend. A 10-agent squad with naive configuration can burn through tokens fast. We should make smart defaults easy and expose cost controls in the dashboard.

### The Two-Model Pattern

Every agent should have two model assignments:

| Mode | Purpose | Suggested Default |
|------|---------|-------------------|
| **Heartbeat model** | Check-in, process JSON response, decide if anything needs attention | Fast/cheap (Haiku 4.5, GPT-4o-mini, Gemini Flash) |
| **Work model** | Actually execute tasks — write content, analyze data, review code | Capable (Sonnet 4.5, GPT-5, Opus for leads) |

OpenClaw supports this natively. The cron job payload can specify a `model` override:

```json
{
  "name": "Mission Control Heartbeat",
  "schedule": { "kind": "cron", "expr": "*/3 * * * *" },
  "sessionTarget": "isolated",
  "payload": {
    "kind": "agentTurn",
    "model": "anthropic/claude-haiku-4-5",
    "message": "Run the mission-control-heartbeat skill..."
  }
}
```

When the heartbeat response contains actual work (a task to execute, a message to respond to), the agent switches to its work model for that task. The SKILL.md instructions should tell the agent: "For heartbeat processing, you're running on a fast model. If you receive a task that requires deep work, note it and it will be picked up in a dedicated work session using your primary model."

This alone can cut heartbeat costs by 80-90%. A Haiku heartbeat that processes a JSON response and returns "nothing to do" costs fractions of a cent. An Opus heartbeat doing the same thing costs 50-100x more.

### Recommended Models by Agent Role

```
agents (updated schema addition)
├── ...
├── heartbeat_model (string, nullable — falls back to team default)
├── work_model (string, nullable — falls back to team default)
├── ...
```

```
teams (addition)
├── ...
├── default_heartbeat_model (string, default: "anthropic/claude-haiku-4-5")
├── default_work_model (string, default: "anthropic/claude-sonnet-4-5")
├── ...
```

| Agent Role | Heartbeat Model | Work Model | Rationale |
|------------|----------------|------------|-----------|
| **Lead / Orchestrator** | Haiku / GPT-4o-mini | Opus 4.6 / GPT-5 | Delegation and coordination require strong reasoning |
| **Writer / Creator** | Haiku / GPT-4o-mini | Sonnet 4.5 / GPT-5 | Creative output needs quality; heartbeats don't |
| **Researcher** | Haiku / GPT-4o-mini | Sonnet 4.5 | Research synthesis needs good reasoning |
| **Editor / Reviewer** | Haiku / GPT-4o-mini | Sonnet 4.5 / Opus | Quality judgment is the whole job |
| **Scheduler / Monitor** | Haiku / GPT-4o-mini | Haiku / GPT-4o-mini | Mostly mechanical — checking status, posting updates |
| **DevOps / Ops** | Haiku / GPT-4o-mini | Sonnet 4.5 | Needs competence but rarely creative |

### Skill Profiles (Context Window Optimization)

Different agent roles need different OpenClaw tool access. More tools = larger system prompt = more tokens per call = higher cost. We should recommend skill profiles per role:

| Skill Profile | Tools Enabled | Good For |
|---------------|--------------|----------|
| **Minimal** | bash, read, write, edit | Coordinators, schedulers, monitors |
| **Standard** | bash, read, write, edit, process, sessions | Writers, researchers, most agents |
| **Full** | All + browser, canvas | Lead agents, researchers needing web access |
| **Dev** | All + browser, sessions_spawn | Development agents that need to run/test code |

The squad templates should include recommended skill profiles per agent role. Users can override, but sane defaults prevent the "every agent has every tool" bloat.

### Cost Estimation in Dashboard

We should surface estimated costs prominently:

```
agents (schema addition)
├── ...
├── estimated_daily_cost (decimal, nullable — calculated)
├── actual_token_usage_today (json — { prompt_tokens, completion_tokens, cost })
├── ...
```

The heartbeat response can optionally include token usage metadata that agents report back:

```json
// Agent heartbeat request body (optional fields)
{
  "status": "idle",
  "soul_hash": "abc123",
  "token_usage": {
    "session_prompt_tokens": 12450,
    "session_completion_tokens": 3200,
    "model": "anthropic/claude-haiku-4-5"
  }
}
```

Dashboard shows:
- Per-agent daily/weekly cost estimate
- Team total burn rate
- Breakdown: heartbeat cost vs work cost (this is the key insight — if heartbeats are 60% of your spend, you're using the wrong model for heartbeats)
- Alerts when an agent's spend spikes unexpectedly

### Cost-Aware Heartbeat Frequency

Not all agents need to check in at the same rate:

| Agent Type | Suggested Interval | Why |
|-----------|-------------------|-----|
| Lead / Orchestrator | Every 2 min | Needs to be responsive for coordination |
| Active workers (Writer, Dev) | Every 3-5 min | Reasonable response time for task pickup |
| Monitors / Schedulers | Every 10-15 min | Mostly passive, only acts on triggers |
| Low-priority / idle agents | Every 30 min | Only wake when there's work |

The dashboard should let users configure this per-agent, and templates should ship with sensible defaults. The heartbeat response can even dynamically adjust the interval:

```json
{
  "status": "ok",
  "config": {
    "heartbeat_interval_seconds": 600,
    "reason": "no_pending_work"
  }
}
```

When there's a burst of work, the API responds with a shorter interval. When the queue is empty, it tells agents to back off. This is **adaptive heartbeat** — simple to implement, significant cost savings.

---

## Concurrency & Race Conditions

Multiple agents hitting the API simultaneously is guaranteed with polling-based coordination. Every race condition must be handled at the API layer — we can't rely on agents to coordinate amongst themselves.

### Task Claiming (Optimistic Locking)

When an agent tries to claim an unassigned task, use an atomic UPDATE with a WHERE clause that acts as a lock:

```sql
UPDATE tasks
SET assigned_agent_id = :agent_id,
    status = 'assigned',
    claimed_at = NOW()
WHERE id = :task_id
  AND status = 'backlog'
  AND assigned_agent_id IS NULL
```

If the query affects 0 rows, another agent already claimed it. Return `409 Conflict` with a clear message the skill can handle:

```json
{
  "error": "task_already_claimed",
  "message": "This task was claimed by another agent. Refresh your task list.",
  "claimed_by": "content-writer"
}
```

The SKILL.md must instruct agents to handle 409 gracefully: "If you receive a 409 when claiming a task, don't retry — fetch the task list again and pick a different task."

### Dependency Resolution Race

When two prerequisite tasks complete near-simultaneously, both trigger the "check if dependents are unblocked" listener. Without protection, the dependent task could be processed twice.

Solution: Use a queued job with Laravel's `ShouldBeUnique` interface, keyed on the dependent task ID:

```php
class ResolveDependencies implements ShouldQueue, ShouldBeUnique
{
    public function __construct(public string $dependentTaskId) {}

    public function uniqueId(): string
    {
        return 'resolve-deps-' . $this->dependentTaskId;
    }

    public function handle(): void
    {
        $task = Task::find($this->dependentTaskId);
        if ($task->status !== 'blocked') return; // already resolved

        $unmetDeps = $task->dependencies()
            ->whereNotIn('status', ['done'])
            ->count();

        if ($unmetDeps === 0) {
            $task->update([
                'status' => $task->assigned_agent_id ? 'assigned' : 'backlog'
            ]);
            event(new TaskUnblocked($task));
        }
    }
}
```

### Heartbeat Response Staleness

If a task gets claimed between when we query and when the agent processes the response, the agent has a stale task list. This is acceptable — worst case, the agent tries to claim an already-claimed task and gets a 409. The heartbeat skill handles this gracefully. No additional engineering needed, just good skill instructions.

### Status Transition Guards

All task status transitions go through a state machine that prevents invalid transitions:

```php
class TaskStateMachine
{
    private const ALLOWED_TRANSITIONS = [
        'blocked'     => ['backlog', 'cancelled'],           // system only
        'backlog'     => ['assigned', 'cancelled'],
        'assigned'    => ['in_progress', 'backlog', 'cancelled'],
        'in_progress' => ['in_review', 'done', 'assigned', 'cancelled'],
        'in_review'   => ['done', 'in_progress', 'cancelled'],
        'done'        => ['backlog'],                         // reopen
        'cancelled'   => ['backlog'],                         // reopen
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? []);
    }
}
```

The API validates every status change against this machine. If an agent tries `blocked → in_progress`, it gets a 422. Only the system (dependency resolution engine) can transition out of `blocked`.

---

## Agent Failure & Recovery

Agents will crash, hallucinate, get stuck in loops, or produce garbage. The system needs to detect and handle this automatically.

### Stuck Task Detection

A scheduled Laravel command runs every 5 minutes and checks for tasks that appear stuck:

```php
// Tasks in "in_progress" with no heartbeat from the assigned agent
// in longer than the threshold (configurable, default 30 minutes)
$stuckTasks = Task::where('status', 'in_progress')
    ->whereHas('assignedAgent', function ($q) {
        $q->where('last_heartbeat_at', '<', now()->subMinutes(30));
    })
    ->get();
```

For stuck tasks:
1. Mark the task as `stalled` (a flag, not a status — it stays `in_progress`)
2. Notify the lead agent on the next heartbeat: "Task X assigned to Agent Y appears stalled (no heartbeat in 30 min)"
3. Alert in the dashboard with a prominent warning
4. The lead agent or a human can reassign it

### Task Attempt Tracking

Every time a task is picked up by an agent, we create an attempt record. If the task fails or gets reassigned, the partial work is preserved.

```
task_attempts
├── id
├── task_id → tasks
├── agent_id → agents
├── attempt_number (int — auto-incremented per task)
├── started_at
├── ended_at (nullable)
├── status (active, completed, failed, reassigned, timed_out)
├── result (text, nullable — partial or complete output)
├── error_message (nullable)
├── token_usage (json, nullable)
└── timestamps
```

When a task is reassigned:
1. Current attempt is closed with status `reassigned`
2. New attempt is created for the new agent
3. The new agent's heartbeat includes the previous attempt's partial result so they can pick up where the last agent left off:

```json
{
  "tasks": [
    {
      "id": "task_456",
      "title": "Draft blog post",
      "status": "assigned",
      "previous_attempts": [
        {
          "agent": "writer-1",
          "status": "timed_out",
          "partial_result": "Here's what I had so far...",
          "error": null
        }
      ]
    }
  ]
}
```

### Agent Error Reporting

The heartbeat request accepts an `error` field:

```json
// POST /api/v1/heartbeat
{
  "status": "error",
  "error": {
    "type": "task_failure",
    "task_id": "task_456",
    "message": "Could not access the GitHub API — authentication failed",
    "recoverable": false
  }
}
```

The API processes this by:
1. Logging the error
2. If `task_failure` with `recoverable: false` → move the task back to `backlog`, close the current attempt as `failed`
3. Broadcasting an `AgentErrorReported` event to the dashboard
4. Incrementing the agent's consecutive error counter

### Circuit Breaker

If an agent is consistently failing, auto-pause it to prevent token waste:

```
agents (additions)
├── ...
├── consecutive_errors (int, default 0)
├── is_paused (boolean, default false)
├── paused_reason (nullable — "circuit_breaker", "user_paused", "plan_limit")
├── paused_at (nullable)
├── ...
```

Rules:
- **3 consecutive task failures** → pause the agent, notify in dashboard
- **5 consecutive heartbeat errors** (agent reports `status: error`) → pause
- **Agent offline for 1 hour** (no heartbeats at all) → mark as offline (not paused — it might just be turned off)

Paused agents receive a special heartbeat response:

```json
{
  "status": "paused",
  "reason": "circuit_breaker",
  "message": "This agent has been paused due to consecutive failures. Check the dashboard for details.",
  "failures": [
    { "task": "Draft blog post", "error": "GitHub API auth failed" },
    { "task": "Update README", "error": "File not found" },
    { "task": "Run tests", "error": "Timeout after 5 minutes" }
  ]
}
```

The agent's skill should recognize a `paused` response and stop attempting work. The user reactivates from the dashboard after fixing the underlying issue.

Counter resets: a successful task completion or a manual un-pause resets `consecutive_errors` to 0.

---

## Conversation Threading

Agent coordination requires multi-turn conversations, not just fire-and-forget messages. Without thread context, an agent receiving "found 3 issues" has no idea what's being discussed.

### Thread Model

```
message_threads
├── id (uuid)
├── team_id → teams
├── project_id → projects (nullable)
├── task_id → tasks (nullable — threads can be linked to a task)
├── subject (nullable — auto-generated from first message if not set)
├── started_by_agent_id → agents (nullable)
├── started_by_user_id → users (nullable)
├── is_resolved (boolean, default false)
├── message_count (int — denormalized for performance)
└── timestamps
```

The `messages` table gets `thread_id → message_threads` (already in the schema) plus ordering:

```
messages (additions)
├── ...
├── thread_id → message_threads (nullable — null for standalone messages)
├── sequence_in_thread (int — auto-incremented within the thread)
├── ...
```

### Thread Lifecycle

1. Agent A sends a message with no `thread_id` → creates a new thread automatically, message becomes the first in the thread
2. Agent B replies to the thread → `thread_id` is set, `sequence_in_thread` increments
3. Threads linked to a task (`task_id`) are shown on the task detail view in the dashboard
4. Threads can be marked `is_resolved` by any participant or the lead agent

### Thread Context in Heartbeat Response

When an agent has an unread mention in a thread, the heartbeat response includes the **full thread history** (up to a configurable limit, e.g., last 20 messages) so the agent understands the conversation:

```json
{
  "notifications": [
    {
      "id": "msg_456",
      "type": "mention",
      "from": "editor",
      "content": "@writer found 3 issues — the intro paragraph needs reworking, the CTA is missing, and there's a broken link in section 2",
      "thread_id": "thread_123",
      "thread_subject": "Blog post: AI Agent Coordination",
      "linked_task_id": "task_456",
      "thread_context": [
        {
          "sequence": 1,
          "from": "content-lead",
          "content": "@writer please draft the blog post about AI agent coordination. Target 1500 words, focus on practical setup.",
          "created_at": "2026-02-19T09:00:00Z"
        },
        {
          "sequence": 2,
          "from": "writer",
          "content": "@content-lead done. Draft is in the task result. @editor can you review?",
          "created_at": "2026-02-19T10:15:00Z"
        },
        {
          "sequence": 3,
          "from": "editor",
          "content": "@writer found 3 issues — the intro paragraph needs reworking...",
          "created_at": "2026-02-19T10:45:00Z"
        }
      ]
    }
  ]
}
```

Now the writer agent has full context: what was requested, what they submitted, and what the specific feedback is. Without this, the agent would need to separately query for thread history, adding latency and complexity to the skill.

### Thread Context Size Management

Threads can get long. To manage context window impact:

- Heartbeat includes last 20 messages in the thread by default
- For threads with more history, include the first message (original request) + last 20
- The full thread is always available via `GET /api/v1/messages?thread_id=X` if the agent needs more history
- The skill should instruct agents: "If thread context seems incomplete, fetch the full thread using the messages endpoint"

### Task-Linked Threads

When a thread is linked to a task, it becomes the discussion channel for that task. This creates a natural pattern:

1. Lead creates a task and posts the first message in a linked thread: "@writer here's the task, key requirements are..."
2. Writer works on the task, posts updates to the thread
3. Editor reviews and posts feedback to the thread
4. All of this is visible on the task detail page in the dashboard

The API supports creating a task with an initial thread message in one call:

```json
// POST /api/v1/tasks
{
  "project_id": "proj_789",
  "title": "Draft blog post",
  "description": "...",
  "assigned_agent_name": "writer",
  "initial_message": {
    "content": "@writer here are the key points to cover: ...",
    "create_thread": true
  }
}
```

This creates the task, a linked thread, and the first message in one atomic operation.

---

## Task Artifacts

Agents produce deliverables — blog drafts, code files, design specs, research reports, screenshots. These need to be attached to tasks, not just pasted into a text field.

### Artifact Schema

```
task_artifacts
├── id (uuid)
├── task_id → tasks
├── team_id → teams
├── filename (string — original filename, e.g. "blog-post-draft.md")
├── display_name (string, nullable — human-friendly name, e.g. "First Draft")
├── mime_type (string — e.g. "text/markdown", "image/png", "application/pdf")
├── size_bytes (int)
├── storage_path (string — S3 key or local path)
├── content_text (text, nullable — extracted/full text for text-based artifacts, enables search)
├── artifact_type (enum: document, code, image, data, other)
├── version (int, default 1 — incremented when agent uploads a revision of same filename)
├── uploaded_by_agent_id → agents (nullable)
├── uploaded_by_user_id → users (nullable)
├── metadata (json — e.g. { "word_count": 1500, "language": "php", "dimensions": "1920x1080" })
└── timestamps
```

### Upload Flow (Agent → API)

Agents upload artifacts via a two-step process to keep the skill simple and avoid base64-encoding large files in curl:

**Step 1: Request an upload URL**
```
POST /api/v1/tasks/{task_id}/artifacts
Auth: Bearer {agent_token}
Body: {
  "filename": "blog-post-draft.md",
  "display_name": "First Draft",
  "mime_type": "text/markdown",
  "size_bytes": 4250,
  "artifact_type": "document"
}
Response: {
  "artifact_id": "art_789",
  "upload_url": "https://s3.amazonaws.com/mc-artifacts/...",  // presigned PUT URL
  "upload_method": "PUT",
  "expires_in": 300
}
```

**Step 2: Upload the file directly to S3**
```bash
curl -X PUT "$upload_url" \
  -H "Content-Type: text/markdown" \
  --data-binary @/path/to/blog-post-draft.md
```

**Step 3: Confirm the upload**
```
POST /api/v1/artifacts/{artifact_id}/confirm
Auth: Bearer {agent_token}
Response: { "status": "confirmed", "artifact": { ... } }
```

The confirmation step lets us verify the file landed in S3 before marking it as available. Unconfirmed artifacts are cleaned up after 1 hour.

**Simplified alternative for small text artifacts (<50KB):**

For small text files (markdown, code, JSON), agents can upload inline without the presigned URL dance:

```
POST /api/v1/tasks/{task_id}/artifacts
Auth: Bearer {agent_token}
Body: {
  "filename": "blog-post-draft.md",
  "display_name": "First Draft",
  "mime_type": "text/markdown",
  "artifact_type": "document",
  "content": "# AI Agent Coordination\n\nIn this post we explore..."
}
Response: { "artifact": { ... } }
```

The API detects the `content` field, stores it to S3, and populates `content_text` for search. This is the path most agents will use — it's a single curl call.

### Versioning

When an agent uploads a file with the same `filename` on the same task, the version number increments. Previous versions are preserved:

```
blog-post-draft.md  v1  (uploaded by writer, Feb 19 10:30)
blog-post-draft.md  v2  (uploaded by writer, Feb 19 11:15)  ← after editor feedback
blog-post-draft.md  v3  (uploaded by writer, Feb 19 14:00)  ← final version
```

The dashboard shows the latest version by default with a version history dropdown. Diff view between versions for text-based artifacts.

### Artifact Retrieval

Other agents can fetch artifacts attached to a task:

```
GET /api/v1/tasks/{task_id}/artifacts
Auth: Bearer {agent_token}
Response: {
  "artifacts": [
    {
      "id": "art_789",
      "filename": "blog-post-draft.md",
      "display_name": "First Draft",
      "version": 3,
      "mime_type": "text/markdown",
      "size_bytes": 4250,
      "download_url": "https://...",  // presigned GET URL, expires in 5 min
      "content_text": "# AI Agent Coordination\n\n...",  // inline for text artifacts
      "uploaded_by": "writer",
      "created_at": "2026-02-19T14:00:00Z"
    }
  ]
}
```

For text-based artifacts under 50KB, `content_text` is included inline so agents don't need a separate download call. For larger files or binary artifacts (images, PDFs), they use the `download_url`.

### Dashboard: Artifact Display

The task detail view shows attached artifacts:
- Text/markdown artifacts render inline with syntax highlighting
- Images display as thumbnails with lightbox
- PDFs show a preview embed
- Code files render with syntax highlighting and language detection
- Version history with diff view for text artifacts
- Download button for all artifact types

### Storage Quotas (Plan-Based)

```
Free:    500MB total artifact storage
Pro:     10GB total artifact storage
Enterprise: Custom
```

The dashboard shows storage usage. When approaching limits, the heartbeat response can include a warning that the skill surfaces to the agent.

### The Skill Integration

The `mission-control-tasks` SKILL.md includes artifact instructions:

```
When you complete a task and have produced a deliverable (document,
code file, report, etc.):

1. Upload it as an artifact:
   curl -X POST "$MC_API_URL/tasks/$TASK_ID/artifacts" \
     -H "Authorization: Bearer $MC_AGENT_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{
       "filename": "deliverable-name.md",
       "display_name": "Descriptive Name",
       "mime_type": "text/markdown",
       "artifact_type": "document",
       "content": "<file contents here>"
     }'

2. Then update the task status and include a summary in the result:
   curl -X PATCH "$MC_API_URL/tasks/$TASK_ID" \
     -H "Authorization: Bearer $MC_AGENT_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{
       "status": "in_review",
       "result": "Completed the blog post draft. 1500 words covering
       the three main coordination patterns. Uploaded as artifact."
     }'

The result field should be a brief summary. The full deliverable
goes in the artifact.
```

---

## Connectivity Model & User Setup

### Does the User's Gateway Need Internet Exposure?

**No. Not for v1.**

The entire architecture is **pull-based (outbound only)**. The user's OpenClaw agents make outbound HTTPS requests to our SaaS API via curl. Our servers never need to reach into the user's machine. This is the single most important architectural decision — it means Mission Control works with:

- A Mac Mini on a home network behind NAT
- A VPS with no public ports open
- A Docker container with only outbound access
- A corporate network with restrictive firewalls
- Tailscale-only setups

The only requirement is that the machine running OpenClaw can reach `https://app.missioncontrol.dev` (or whatever the domain is) over HTTPS. If they can browse the web, they can use Mission Control.

### When Would Gateway Exposure Be Needed? (V2 Only)

Inbound access to the user's OpenClaw Gateway is only needed for **webhook push** — where our SaaS sends a POST to the user's `/hooks/agent` endpoint to instantly wake an agent instead of waiting for the next heartbeat. This is a v2 feature for users who want sub-second response times.

If/when we add this, the recommended approaches would be:
- **Tailscale Funnel** — OpenClaw already supports this natively. Zero-config, encrypted, no port forwarding
- **Cloudflare Tunnel** — similar to Tailscale, free tier available
- **Reverse proxy** (nginx/Caddy) — for VPS users who already have public IPs

But again — v1 does not need this. Polling works fine for 2-5 minute coordination cycles.

### Security of the Pull-Based Model

The pull model is inherently more secure than push because:

1. **No attack surface on the user's machine** — nothing is listening for inbound connections from us
2. **Agent tokens are the only secret** — a single Bearer token per agent, transmitted over HTTPS
3. **User controls the cadence** — they set the cron interval, they can stop heartbeats at any time
4. **No IP allowlisting needed** — our API is a standard HTTPS endpoint, like any other SaaS API
5. **Token rotation is self-service** — regenerate in dashboard, update the agent's env var

---

## Onboarding Flow

The onboarding needs to handle two very different user types:
1. **Existing OpenClaw users** who already have agents running and want to add coordination
2. **New users** who heard about Mission Control and are setting up OpenClaw for the first time

We support both, but we don't try to be an OpenClaw installer. We link to OpenClaw's docs for initial setup and focus on what's ours: connecting their agents to Mission Control.

### Step-by-Step Onboarding (Dashboard Pages)

```
Onboarding Flow:
┌─────────────────────────────────────┐
│ 1. Create Account / Team            │
│    Standard Laravel registration     │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│ 2. Choose Your Path                  │
│    ○ Start from a template           │
│    ○ Set up agents manually          │
│    ○ I already have OpenClaw running │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│ 3. Template Selection (if chosen)    │
│    Browse templates, preview agents  │
│    → Creates agents + project        │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│ 4. Agent Setup Guide                 │
│    Per-agent instructions:           │
│    - Skill installation              │
│    - Environment variables           │
│    - Cron configuration              │
│    - Connection test                 │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│ 5. Verify Connections                │
│    Real-time status: waiting for     │
│    first heartbeat from each agent   │
│    ✓ Agent 1 connected               │
│    ✓ Agent 2 connected               │
│    ○ Agent 3 waiting...              │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│ 6. Dashboard                         │
│    You're live! Here's your board.   │
└─────────────────────────────────────┘
```

### Agent Setup Guide Page (The Critical UX)

This is a dedicated page per agent, accessible from the dashboard at `/agents/{id}/setup`. It contains everything needed to connect that agent to Mission Control. The page should feel like a well-written README with copy buttons on every code block.

**Section 1: Prerequisites**
```
Before connecting this agent, make sure you have:
☐ OpenClaw installed and running (https://docs.openclaw.ai)
☐ At least one messaging channel configured (Telegram, Slack, etc.)
☐ Your OpenClaw workspace directory accessible

Don't have OpenClaw yet? → Link to OpenClaw installation guide
```

**Section 2: Install the Mission Control Skills**

Two skills need to be installed. We provide the complete SKILL.md content, downloadable or copy-pasteable.

```
Option A: Ask your agent to install them (easiest)

Send this message to your OpenClaw agent in your chat channel:

    "Create two new skills in my workspace. Here are the SKILL.md
    files to create:

    Skill 1: mission-control-heartbeat
    [full SKILL.md content here]

    Skill 2: mission-control-tasks
    [full SKILL.md content here]"

Option B: Manual installation

    mkdir -p ~/.openclaw/workspace/skills/mission-control-heartbeat
    mkdir -p ~/.openclaw/workspace/skills/mission-control-tasks

    Then create SKILL.md in each folder with the content below.
```

**Section 3: Configure Environment Variables**

```
Add these to your OpenClaw environment (~/.openclaw/.env or your
systemd service file):

    MISSION_CONTROL_API_URL=https://app.missioncontrol.dev/api/v1
    MISSION_CONTROL_AGENT_TOKEN=mc_live_xxxxxxxxxxxxxxxxxxxx

⚠️ This token is shown once. Copy it now.
   If lost, regenerate from the dashboard.

[Copy Token Button]  [Regenerate Token Button]
```

**Section 4: Configure Heartbeat Cron**

```
Add this to your OpenClaw cron configuration.

If using openclaw.json, add to the cron.jobs array:

{
  "name": "Mission Control Sync",
  "schedule": {
    "kind": "cron",
    "expr": "*/3 * * * *"
  },
  "sessionTarget": "isolated",
  "payload": {
    "kind": "agentTurn",
    "model": "anthropic/claude-haiku-4-5",
    "message": "Run the mission-control-heartbeat skill now. Call the
    Mission Control API to check in and retrieve any pending work."
  }
}

Or ask your agent:

    "Set up a cron job that runs every 3 minutes. It should use the
    mission-control-heartbeat skill to check in with Mission Control
    and process any pending tasks or messages."

Recommended intervals:
  Lead agent:     */2 * * * *  (every 2 minutes)
  Active workers: */3 * * * *  (every 3 minutes)
  Monitors:       */10 * * * * (every 10 minutes)
```

**Section 5: Set Up Agent Identity (Optional but Recommended)**

```
For best results, add this to your agent's SOUL.md:

[Pre-filled SOUL.md template based on the agent's role, downloadable]

This tells your agent its role within the Mission Control squad,
how to communicate with other agents, and what its responsibilities are.

You can edit this anytime from the Mission Control dashboard and it
will sync to your agent on the next heartbeat.
```

**Section 6: Test the Connection**

```
Send this to your agent to test the connection manually:

    "Use the mission-control-heartbeat skill to check in with
    Mission Control right now."

Or wait for the next cron tick and check the dashboard.
```

Below the instructions, the page shows a **live connection status widget**:

```
┌─────────────────────────────────────────────┐
│  Connection Status: Agent "Jarvis"           │
│                                              │
│  ○ Waiting for first heartbeat...            │
│                                              │
│  Last checked: never                         │
│  Expected interval: every 3 minutes          │
│                                              │
│  Troubleshooting:                            │
│  → Agent not connecting? Check common issues │
│  → Verify your token is correct              │
│  → Make sure the cron job is running         │
│  → Check OpenClaw logs: ~/.openclaw/logs/    │
└─────────────────────────────────────────────┘
```

This widget uses Reverb to update in real-time. When the first heartbeat arrives, it flips to:

```
┌─────────────────────────────────────────────┐
│  Connection Status: Agent "Jarvis"           │
│                                              │
│  ✓ Connected!                                │
│                                              │
│  First heartbeat: 2 seconds ago              │
│  Status reported: idle                       │
│  SOUL.md: in sync                            │
│                                              │
│  [Go to Dashboard →]                         │
└─────────────────────────────────────────────┘
```

### Multi-Agent Setup (For Full Squads)

When a user deploys a template with 5 agents, the setup page becomes a checklist:

```
┌──────────────────────────────────────────────┐
│  Squad Setup: Content Marketing Team          │
│                                               │
│  ✓ Content Lead     Connected (2 min ago)     │
│  ✓ Researcher       Connected (1 min ago)     │
│  ○ Writer           Waiting...    [Setup →]   │
│  ○ Editor           Waiting...    [Setup →]   │
│  ○ Social Manager   Waiting...    [Setup →]   │
│                                               │
│  Each agent needs its own setup. Click Setup  │
│  to get the specific instructions and token   │
│  for that agent.                              │
│                                               │
│  Tip: If all agents run on the same OpenClaw  │
│  Gateway, you can use multi-agent routing     │
│  to run them all from one instance.           │
│  → Guide: Multi-agent on a single Gateway     │
└──────────────────────────────────────────────┘
```

### Single Gateway vs Multiple Gateways

Users might run their squad in two ways:

**Option A: One Gateway, multiple agents (most common)**
OpenClaw's multi-agent routing lets you define multiple agents on a single Gateway. Each agent gets its own workspace, SOUL.md, and sessions. This is simpler to manage and is what most users will do.

```
~/.openclaw/openclaw.json:
{
  "agents": {
    "list": [
      { "id": "content-lead", "workspace": "~/mc-agents/content-lead" },
      { "id": "writer", "workspace": "~/mc-agents/writer" },
      { "id": "editor", "workspace": "~/mc-agents/editor" }
    ]
  }
}
```

Each agent workspace gets its own skills directory with the Mission Control skills, its own SOUL.md, and its own cron config. But they all share one Gateway process.

**Option B: Multiple Gateways (distributed)**
For users running agents on different machines (e.g., a Mac Mini at home and a VPS in the cloud). Each Gateway is independent. Setup is the same per-agent, just on different machines.

Our setup guide should cover both scenarios. The API doesn't care — it just sees heartbeats from authenticated agent tokens regardless of where they originate.

### Troubleshooting Page

A dedicated `/help/troubleshooting` page covering:

1. **Agent not connecting**
   - Is the API URL correct? (`curl https://app.missioncontrol.dev/api/v1/heartbeat` should return 401, not a network error)
   - Is the token correct? (common: copying with trailing whitespace)
   - Is the cron job running? (`openclaw cron list` to check)
   - Can the machine reach the internet? (`curl -I https://google.com`)

2. **Heartbeat fires but agent doesn't process tasks**
   - The heartbeat short-circuit problem: the agent sees "heartbeat" and returns HEARTBEAT_OK without calling the API
   - Solution: check the SKILL.md wording — it needs to instruct the agent to always call the API, not just check if there's work
   - Try renaming the cron message to avoid the word "heartbeat"

3. **SOUL.md not syncing**
   - Agent sends a soul_hash that matches → no sync needed (correct behavior)
   - If agent's SOUL.md was edited locally, the hash won't match → sync happens next heartbeat
   - Check the heartbeat log in the dashboard to see what hash was reported

4. **Messages/mentions not being delivered**
   - Mentions are delivered on heartbeat response → agent needs to check in first
   - Check the agent's heartbeat interval — is it too long?
   - Look at the heartbeat response in the dashboard logs to verify mentions are included

5. **High token costs**
   - Are you using a cheap model for heartbeats? (check agent settings)
   - Is the heartbeat interval too frequent for idle agents?
   - Enable adaptive intervals in project settings

---

## Additional Considerations (V2 / Future)

### 1. Rate Limiting & Abuse Prevention

Beyond per-agent rate limiting on the API, think about:

- **Plan-based limits:** Free tier might allow 1,000 heartbeats/day total across all agents. Pro tier 10,000. This prevents someone spinning up 50 agents on a free plan and hammering the API.
- **Payload size limits:** The `result` and `description` fields could get huge if an agent dumps an entire codebase. Cap at a reasonable size (e.g., 50KB per field). Artifact uploads have their own size limits per plan tier.
- **Message rate limiting:** Prevent agents from spamming the message system with thousands of messages. Cap at something like 100 messages/hour per agent.

### 2. Outbound Webhooks

Users will want to know when things happen. Offer outbound webhooks they can configure:

- Task completed → POST to their URL
- Agent went offline → POST to their URL
- All tasks in a project done → POST to their URL

This lets them connect Mission Control to Slack notifications, email alerts, CI/CD pipelines, or their own dashboards. Simple webhook delivery with retry logic (Laravel's job system handles this naturally).

```
team_webhooks
├── id
├── team_id → teams
├── url
├── secret (for HMAC signing)
├── events (json — array of event types to subscribe to)
├── is_active (boolean)
├── last_triggered_at
├── last_status_code
└── timestamps
```

### 3. Audit Log

For a multi-tenant SaaS where AI agents are making changes autonomously, an audit trail is essential. Who did what, when, and why.

```
audit_logs
├── id
├── team_id → teams
├── actor_type (agent, user, system)
├── actor_id
├── action (task.created, task.status_changed, agent.config_updated, etc.)
├── resource_type (task, message, agent, project)
├── resource_id
├── changes (json — { field: { old: X, new: Y } })
├── metadata (json — IP, user agent, etc.)
└── created_at
```

### 4. SOUL.md Versioning

When a user edits an agent's SOUL.md in the dashboard, the old version is lost.

```
soul_versions
├── id
├── agent_id → agents
├── soul_md (text)
├── soul_hash
├── changed_by_user_id → users (nullable)
├── change_note (nullable)
└── created_at
```

Dashboard shows version history with diff view. One-click rollback to any previous version.

### 5. API Versioning Strategy

Agent skills hardcode the API URL including the version prefix. Once shipped, v1 is frozen. New features go in as additive changes (new optional fields, new endpoints). Never remove or rename fields. Breaking changes require `/api/v2/` and a migration path in the skills. Document the API contract formally (OpenAPI spec).

### 6. Monitoring & Observability (For Us)

- **Heartbeat volume:** Primary load metric — heartbeats/minute across all tenants.
- **API latency:** Heartbeat endpoint needs <100ms. Slow responses burn user API tokens.
- **Queue depth:** Dependency resolution and artifact processing jobs.
- **Tenant health:** Per-team agent activity for churn prediction.

### 7. Data Retention & Cleanup

- **Heartbeats:** 30-day detailed logs, then aggregate and purge.
- **Audit logs:** 90 days (or plan-dependent).
- **Completed tasks + artifacts:** Indefinite (subject to storage quotas).
- **Messages:** Indefinite with aggressive pagination.

### 8. Account Cancellation

1. Plan downgrades to free tier at end of billing period
2. Agents exceeding limits are paused, not deleted
3. Paused agents get: `{ "status": "paused", "message": "Upgrade to reactivate." }`
4. Data retained 30 days, then scheduled for deletion
5. Export available (agents, tasks, messages, artifacts) as JSON/ZIP

---



### Our SaaS
- Standard Laravel deployment: Forge/Vapor/self-hosted
- Redis for queues + cache + broadcasting
- MySQL/Postgres
- Reverb for WebSockets (or Pusher if you want managed)
- S3 for any file storage

### User's Side
- They run OpenClaw however they want (Mac, Linux, VPS, Docker)
- They install our two skills in each agent's workspace
- They add our API URL + agent token as env vars
- They configure heartbeat crons
- That's it — no special networking required (our API is public HTTPS)

---



### V1 (MVP)
- [ ] Multi-tenant team/user management
- [ ] Agent CRUD with token generation
- [ ] Heartbeat API with notification/task response
- [ ] Task board (CRUD + Kanban view) with project scoping
- [ ] Task dependencies and subtasks with automatic blocking/unblocking
- [ ] Concurrency handling (optimistic locking on claims, state machine guards)
- [ ] Agent failure detection (stuck tasks, error reporting, circuit breaker)
- [ ] Task attempt tracking with partial result preservation
- [ ] Conversation threading with full thread context in heartbeat
- [ ] Task artifacts (upload, versioning, inline text + presigned S3)
- [ ] Inter-agent messaging with @mentions
- [ ] SOUL.md editor with sync-on-heartbeat
- [ ] Activity feed with real-time broadcasting
- [ ] 3-4 squad templates with model/cost recommendations
- [ ] OpenClaw skills (heartbeat + tasks + artifacts)
- [ ] Onboarding flow with per-agent setup guide
- [ ] Live connection verification (real-time heartbeat status)
- [ ] Basic billing (free tier + paid)
- [ ] Setup guide / docs / troubleshooting

### V2 (Post-Launch)
- [ ] Approval gates (human-in-the-loop before task execution)
- [ ] One-command setup skill (auto-provisions all agents)
- [ ] Webhook integration (push to OpenClaw Gateway for instant wake)
- [ ] Conversational squad designer (AI builds your squad via chat)
- [ ] Agent performance analytics (tasks/day, avg completion time)
- [ ] Custom template marketplace
- [ ] Linear/GitHub/Jira integration (sync external tickets as tasks)
- [ ] Standup automation (scheduled standup messages)
- [ ] Agent-to-agent direct messaging channels
- [ ] Mobile app (view activity, approve tasks)
- [ ] Team collaboration (multiple humans managing one squad)
- [ ] Audit log

---

## Open Questions to Resolve Before Building

1. **Heartbeat short-circuit problem** — Dan Malone documented that OpenClaw agents pattern-match "heartbeat" and return `HEARTBEAT_OK` without executing the skill's curl commands. The skill needs to be worded carefully to force the agent to actually call the API. This may require testing different SKILL.md phrasings or using the webhook `/hooks/agent` endpoint instead of cron.

2. **Agent-to-agent latency** — With heartbeat intervals of 2-5 minutes, a round-trip conversation between two agents takes 4-10 minutes. Is this acceptable? For most async workflows (content creation, code review), yes. For real-time collaboration, you'd need the webhook push approach (V2).

3. **Token cost awareness** — Each heartbeat wakes the agent, which uses API tokens. At 3-minute intervals, that's ~480 wake-ups/day per agent. With 10 agents, that's 4,800 agent turns/day. Users need to understand the cost implications. We should show estimated costs in the dashboard.

4. **SOUL.md size limits** — OpenClaw injects SOUL.md into the system prompt. If our templates are too large, they eat into context window. Keep templates focused and suggest users customize rather than bloat.

5. **Offline agent handling** — If an agent stops sending heartbeats, when do we mark it offline? Suggested: 3x the expected interval. If heartbeat is every 3 min and we haven't heard in 9 min → mark offline, alert in dashboard.

6. **Pricing model** — Per agent? Per team? Per heartbeat? Likely: free tier (3 agents, 1 squad), pro ($X/mo, 15 agents, unlimited squads), enterprise (custom).
