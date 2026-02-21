# Phase 8: Home Page + Artifact Viewer + Navigation

## Design Reference
See DESIGN.md for full specs. Key patterns for this phase:

### Agent Cards (Home)
- White card, rounded-xl, shadow-sm, border neutral-200
- Colored initials avatar + agent name (font-medium) + role (slate-500) + status dot
- Current task or "Idle" muted, "Last heartbeat: 2 min ago" in text-xs
- Paused: rose-50 tint + "Paused" badge

### Activity Feed
- Vertical timeline with colored dots on thin line
- Event icons: pulse/sky (heartbeat), arrow/color (task), chat/indigo (message),
  paperclip/violet (artifact), pause/rose (paused), unlock/emerald (unblocked)
- Relative timestamps, filter pills

### Artifact Viewer
- File cards: type icon + filename + size + version badge
- Inline expand: markdown rendered, code highlighted, images lightbox
- Version dropdown + diff view (emerald-50 additions, rose-50 deletions)

### Empty States
- Friendly, centered, clear CTA
- Never a blank white page

## Tasks

### Home / Team Overview Page
1. Custom Filament page set as dashboard default
2. Top row: quick stat cards (inline, not full-width)
   - Tasks completed today (emerald accent)
   - Active projects count
   - Agents online / total (with status breakdown)
   - Estimated daily cost (if data available, otherwise placeholder)
3. Agent grid: cards following design spec
   - Sorted: online first, then idle, then offline/paused
   - 2-3 columns responsive grid
4. Alerts section (only shows if relevant):
   - Paused agents: rose-tinted card with agent name, reason, un-pause link
   - Stuck tasks: amber-tinted card with task name, assigned agent, duration stuck
   - Offline agents: subtle slate card, "last seen X ago"
5. Compact activity feed: last 20 events in timeline format
   - Links to full activity page

### Artifact Viewer
1. Enhance task detail Artifacts tab from Phase 6:
   - File cards row with type icon, filename, size, version badge
   - Click to expand inline:
     - Markdown: rendered with typography, indigo links
     - Code: syntax highlighted with language label (using highlight.js or similar)
     - Images: responsive preview, click for lightbox
     - Other: download button only
2. Version history dropdown: "v3 (latest) | v2 | v1"
   - Selecting old version shows that content
3. Diff view for text artifacts:
   - Side-by-side layout
   - Additions highlighted emerald-50, deletions rose-50
   - Toggle between rendered and diff views
4. Upload from dashboard:
   - Drag-and-drop zone on task detail
   - Or file picker button
   - Uploads via artifact API from Phase 5

### Navigation Sidebar
1. Customize Filament's navigation:
   - "Mission Control" logo/name at top (font-semibold)
   - Group: Overview → Home
   - Group: Projects → each project as nav item with color dot
     - Sub-items: Board, Messages, Activity (expandable on click)
   - Group: Management → Agents (with online count badge, e.g., "7/10"),
     Templates
   - Group: Settings → Team, Billing (placeholder)
   - Bottom section: team name/switcher, user avatar menu, dark mode toggle
2. Active item styling: indigo-50 bg + indigo-600 text + left border accent
3. Projects list updates dynamically (new projects appear)

### Empty States
1. Agents page (no agents): "No agents yet. Deploy a template to get started →"
   with link to templates page
2. Projects page (no projects): "No projects yet. Create one to start
   organizing your agents' work →"
3. Tasks page (no tasks in project): "No tasks in this project. Create one
   or wait for your lead agent to plan work."
4. Kanban board (no tasks): Same as tasks empty state
5. Messages (no threads): "No conversations yet. Agents will communicate
   here when they start working."
6. Artifacts (none on task): "No artifacts yet. Agents will upload
   deliverables here as they complete work."

## Acceptance Criteria
- Home page shows agent cards sorted by status with correct styling
- Quick stats display accurate counts
- Alerts section only shows when relevant issues exist
- Activity feed shows recent events with correct icons and colors
- Artifact viewer renders markdown, code, and images inline
- Version history dropdown works, shows previous versions
- Diff view shows additions/deletions with color highlighting
- Upload from dashboard works (drag-drop and file picker)
- Navigation sidebar has project-centric structure with expandable sub-items
- Agent count badge shows online/total
- Dark mode toggle in sidebar bottom works
- All empty states display with clear CTAs
- Responsive: sidebar collapses, Kanban scrolls horizontally