# Phase 12: Skill Files + Troubleshooting

## Tasks

### OpenClaw Skill Files
1. Generate mission-control-heartbeat/SKILL.md content:
   - Framing: "Sync with Mission Control and process any pending work"
     (avoid the word "heartbeat" in the agent-facing instruction to prevent
     the short-circuit problem — see ARCHITECTURE.md)
   - curl command for POST /api/v1/heartbeat with Bearer token
   - Response processing instructions:
     - Check for tasks: if tasks present, note them for work session
     - Check for notifications: if @mentions with thread_context,
       read the full thread before responding
     - Check for soul_sync: if present, update SOUL.md
     - Check for config changes: update heartbeat interval if changed
   - Error reporting: how to POST error status if something fails
   - 409 handling: "If you get a 409 when claiming a task, don't retry —
     fetch the task list again and pick a different one"
   - Env var references: $MC_API_URL, $MC_AGENT_TOKEN

2. Generate mission-control-tasks/SKILL.md content:
   - Task listing: curl GET /api/v1/tasks with filters
   - Task creation: curl POST with depends_on and parent_task_id examples
   - Task claiming: curl POST /api/v1/tasks/{id}/claim
   - Task updating: curl PATCH with status transitions
   - Message posting: curl POST /api/v1/messages with @mentions and thread_id
   - Thread replies: how to reply to an existing thread
   - Artifact upload (inline): curl POST with content field for text files
   - Artifact upload (presigned): multi-step flow for large files
   - Artifact retrieval: curl GET /api/v1/tasks/{id}/artifacts
   - Env var references: $MC_API_URL, $MC_AGENT_TOKEN

3. Both skills:
   - Clear, natural-language instructions (these are read by LLMs)
   - Exact curl commands matching our API contract
   - Example responses so the agent knows what to expect
   - Error handling guidance for common failure modes
   - Token injected via env var, never hardcoded

4. Store skill content in database (skill_templates table or config)
   so it can be served on the setup guide page with agent-specific values

### Troubleshooting Page
1. Static Filament page accessible from sidebar (Settings → Troubleshooting)
   or linked from setup guide
2. Sections:
   - Agent not connecting: check token, URL, network, cron running
   - Heartbeat short-circuit: agent says "HEARTBEAT_OK" without calling API —
     reword the cron message, avoid "heartbeat" keyword
   - SOUL.md not syncing: explain hash comparison, check if hash matches
   - Messages not delivering: check heartbeat interval, check read_by
   - Tasks stuck in blocked: explain dependency model, check prerequisites
   - High token costs: wrong model for heartbeat, too-frequent interval,
     too many skills loaded
   - Agent paused by circuit breaker: check error messages, fix underlying
     issue, un-pause from dashboard
   - Artifact upload failing: check size limits, S3 configuration,
     confirm step for presigned uploads
3. Each section: problem description, likely cause, step-by-step fix,
   relevant curl commands for manual testing

## Acceptance Criteria
- SKILL.md files contain correct curl commands matching ALL API endpoints
- Skill content avoids "heartbeat" keyword in agent-facing instructions
- Both skills reference env vars correctly ($MC_API_URL, $MC_AGENT_TOKEN)
- Skills include error handling guidance (409, 422, 401, 500)
- Skills include example responses for key endpoints
- Troubleshooting page covers all documented issues
- Each troubleshooting section has actionable steps
- Skill content is stored in DB and served on setup guide with agent values