# Phase 3: Messages, Threading, and Artifacts

## Tasks

### 3a: Message Threading
1. POST /api/v1/messages — create message
   - If no thread_id: auto-create a new message_thread, set this as first message
   - If thread_id provided: append to thread, auto-increment sequence_in_thread,
     increment thread.message_count
   - Parse @mentions from content (regex for @agent-name patterns),
     populate mentions json field
   - Optional: project_id scoping, task linking via initial_message on task create
2. GET /api/v1/messages — list messages
   - Filterable by: thread_id, project_id, unread (mentioning=me), message_type
   - When filtering by thread: return messages ordered by sequence_in_thread
   - When filtering unread mentions: include thread_context (last 20 messages
     in the thread + first message if thread is longer)
3. Thread management:
   - GET /api/v1/threads — list threads for agent's projects
   - PATCH /api/v1/threads/{id} — mark resolved
4. Update POST /api/v1/tasks to support initial_message field:
   - Creates task + thread + first message atomically
   - Links thread to task via task_id

### 3b: Heartbeat Thread Context
1. Update heartbeat response to include thread_context for unread mentions:
   - For each unread mention, include the full thread history
     (first message + last 20 messages)
   - Mark messages as read (add agent to read_by array) after including
     them in heartbeat response
2. Thread context format matches ARCHITECTURE.md spec exactly

### 3c: Task Artifacts
1. POST /api/v1/tasks/{task_id}/artifacts — two upload paths:
   - Inline (small text, <50KB): accepts "content" field, stores to S3,
     populates content_text
   - Presigned (large/binary): returns upload_url (presigned S3 PUT),
     artifact_id for confirmation
2. POST /api/v1/artifacts/{id}/confirm — confirms presigned upload completed
3. GET /api/v1/tasks/{task_id}/artifacts — list artifacts for a task
   - Returns content_text inline for text artifacts <50KB
   - Returns presigned download_url for large/binary artifacts
   - Only latest version by default, ?include_versions=true for history
4. Artifact versioning:
   - Same filename on same task → increment version number
   - Previous versions preserved, accessible via version history
5. Configure S3 (or local disk for dev) for artifact storage
6. Cleanup job: delete unconfirmed artifacts older than 1 hour

### 3d: Artifact Metadata
1. On upload, extract metadata best-effort:
   - Text files: word count, line count
   - Code files: language detection from extension/mime type
   - Images: dimensions (if we can read headers without heavy deps)
2. Store in artifact metadata json field

## Acceptance Criteria
- Message tests:
  - Create message without thread → auto-creates thread
  - Create message with thread_id → appends correctly with sequence
  - @mentions parsed from content correctly
  - Unread messages filtered by mentioning agent
  - Thread context included in heartbeat: first message + last 20
  - Messages marked as read after heartbeat delivery
  - Thread resolution works
- Task with initial_message creates task + thread + message atomically
- Artifact tests:
  - Inline upload stores to S3/disk, populates content_text
  - Presigned flow: request URL → upload → confirm → artifact available
  - Unconfirmed artifacts cleaned up after 1 hour
  - Same filename increments version
  - Previous versions preserved and accessible
  - List artifacts returns content_text inline for small text files
  - List artifacts returns download_url for large/binary
- Thread context size: threads with 50+ messages only return first + last 20