# Phase 7: Kanban Board + Conversations

## Design Reference
See DESIGN.md for full component specs. Key patterns for this phase:

### Kanban Board Design
- Clean white background, columns separated by subtle dividers
- Column headers: text-xs font-semibold uppercase tracking-wider slate-500
  with count pill badge
- Colored underline per column:
  Blocked=rose, Backlog=slate, Assigned=indigo, In Progress=sky,
  In Review=violet, Done=emerald
- Cards: white, rounded-lg, shadow-sm, border, p-3
  - Title: text-sm font-medium, max 2 lines
  - Bottom row: tiny agent avatar (20px), priority dot, dependency icon
  - Blocked: lock icon + "Blocked by: X" in text-xs rose-500
  - Has dependents: chain-link icon
  - Subtasks: indented under parent with thin slate-200 connecting line
  - Hover: shadow-md transition, subtle lift
  - Drag: card follows cursor with slight 2deg rotation,
    drop zone highlights indigo-100

### Conversation Design
- Chat layout, messages top to bottom
- Agent messages: colored initials avatar + name + timestamp, content below
- Human messages: same but indigo-50 background
- @mentions highlighted in indigo-600 font-medium
- Thread sidebar: subject + last message preview + unread count
- Resolved threads: muted (opacity-60) with checkmark

## Tasks

### Kanban Board
1. Custom Filament page registered under each project:
   /projects/{project}/board
2. Livewire component for the Kanban board:
   - Columns: Blocked, Backlog, Assigned, In Progress, In Review, Done
   - Each column header with colored underline accent and task count badge
   - Task cards following the design spec
   - Subtasks rendered nested under parent with connecting line
3. Drag-and-drop:
   - Uses Livewire's SortableJS integration or alpine-sortable
   - Validates against TaskStateMachine before committing move
   - If invalid transition: card snaps back, toast error shown
   - On valid move: PATCH /api/v1/tasks/{id} with new status, update UI
4. Filter pills at top of board:
   - By agent (avatar + name chips)
   - By priority (color dot chips)
   - By tag
5. Click card → opens slide-over panel with task detail
   (reuse TaskResource view content)

### Conversation View
1. Project messages page: /projects/{project}/messages
2. Two-panel layout:
   - Left sidebar: thread list (subject, last message preview, unread badge,
     resolved indicator)
   - Right area: selected thread messages in chat layout
3. Message display:
   - Agent avatar (colored initials) + name + relative timestamp
   - Message content with @mention highlighting
   - Human-sent messages with indigo-50 background
4. Compose area at bottom:
   - Textarea with @mention autocomplete dropdown
   - "Send" button
   - Posts via API, updates UI
5. Thread-to-task link: if thread has linked task, show task title
   + status badge at top of thread
6. Mark resolved action on threads

### Inline Conversation in Task Detail
1. Embed conversation component in task view page's Conversation tab
   - Shows linked thread (if exists), or "Start a conversation" button
   - Same chat layout as project messages page

## Acceptance Criteria
- Kanban board displays tasks in correct columns
- Column headers have correct colored underlines and counts
- Task cards show title, avatar, priority dot, dependency indicators
- Subtasks nested under parents with visual connecting line
- Drag-and-drop moves tasks between columns
- Invalid moves (e.g., blocked → in_progress) snap back with error toast
- Filter pills filter tasks by agent, priority, tag
- Click card opens slide-over with task details
- Conversation page shows threads in sidebar, messages in chat layout
- @mentions highlighted in messages
- Compose and send messages from dashboard
- Thread linked to task shows task status badge
- Resolved threads visually muted
- Task detail has conversation tab with inline thread view