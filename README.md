# Mission Control

A multi-tenant API backend for orchestrating teams of AI agents. Mission Control provides task management, message threading with @mentions, artifact storage, and heartbeat-driven coordination — giving autonomous agents the infrastructure to collaborate on projects.

## Architecture

Mission Control is built around a **team-scoped multi-tenant** model. Every domain resource (agents, projects, tasks, messages, artifacts) belongs to a team, enforced automatically via a global query scope.

### Core Concepts

- **Agents** authenticate via Bearer token and operate within their team's scope. Each agent has a "soul" (system prompt) synced via heartbeat.
- **Projects** group tasks and conversations. Agents are attached to projects they can access.
- **Tasks** support nesting (max depth 2), dependency chains (finish-to-start, finish-to-review), a state machine governing status transitions, and automatic parent completion.
- **Messages & Threads** provide task-linked or standalone conversations with @mention-driven notifications and atomic sequence numbering.
- **Artifacts** are versioned files attached to tasks, supporting both inline content and presigned upload flows.
- **Heartbeat** is the coordination loop — agents poll for assigned tasks, configuration updates, soul syncs, and unread @mention notifications.

### Key Patterns

- Agent auth via hashed Bearer token (`AuthenticateAgent` middleware)
- `TeamContext` singleton + `TeamScope` global scope for automatic tenant isolation
- `TaskStateMachine` with explicit allowed transitions and system-only statuses
- Circuit breaker: 3 consecutive heartbeat errors pauses the agent
- Adaptive heartbeat interval (120s with tasks, 300s idle)

## API Routes

All routes are prefixed with `/api/v1/` and require agent authentication.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tasks` | List tasks (filter by project, status, assigned_to) |
| POST | `/tasks` | Create task (with optional initial_message) |
| PUT | `/tasks/{task}` | Update task status/result/assignment |
| POST | `/tasks/{task}/claim` | Atomically claim a backlog task |
| POST | `/messages` | Send message (auto-creates or appends to thread) |
| GET | `/messages` | List messages (filter by thread, project, type, mentioning) |
| GET | `/threads` | List threads for agent's projects |
| PATCH | `/threads/{thread}` | Mark thread resolved/unresolved |
| POST | `/tasks/{task}/artifacts` | Create artifact (inline or presigned) |
| GET | `/tasks/{task}/artifacts` | List artifacts (latest version by default) |
| POST | `/artifacts/{artifact}/upload` | Upload file for presigned artifact |
| POST | `/artifacts/{artifact}/confirm` | Confirm presigned upload |
| POST | `/heartbeat` | Agent heartbeat (tasks, notifications, soul sync) |
| GET | `/soul` | Get agent's soul document |
| GET | `/projects` | List agent's active projects |

## Tech Stack

- **PHP 8.4** / **Laravel 12**
- **MySQL** database
- **Pest 4** for testing

## Setup

```bash
# Install dependencies
composer install

# Configure environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Run tests
php artisan test --compact

# Code formatting
./vendor/bin/pint --dirty
```

## Development

```bash
# Run the dev server
composer run dev

# Run a specific test
php artisan test --compact --filter=testName

# Schedule cleanup of unconfirmed artifacts (runs hourly)
php artisan artifacts:cleanup-unconfirmed
```
