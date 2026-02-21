# Phase 10: Templates + Onboarding Wizard

## Tasks

### Squad Templates
1. Seed the 4 squad templates from ARCHITECTURE.md:
   - Content Marketing Team (5 agents)
   - Product Development Squad (5 agents)
   - Research & Analysis Team (4 agents)
   - Customer Support Squad (4 agents)
   Each template includes: agent names, roles, SOUL.md template content,
   heartbeat_model, work_model, skill profiles, heartbeat intervals,
   descriptions, estimated daily cost
2. Template gallery Filament page:
   - Card layout: each template as a card with name, description,
     agent count, estimated daily cost
   - Click to expand: shows all agents in the template with their
     roles, models, and a SOUL.md preview
   - "Deploy" action button on each template:
     - Creates all agents with generated tokens
     - Creates a starter project
     - Assigns all agents to the project
     - Sets lead agent
     - Redirects to squad setup checklist (Phase 11)

### Onboarding Wizard
1. Multi-step onboarding flow (Livewire wizard or Filament wizard component):
   - Step 1: Create account/team (standard Laravel registration,
     Filament registration page customization)
   - Step 2: Choose your path (3 options):
     - "Start from a template" → goes to template gallery
     - "Set up agents manually" → goes to agent creation
     - "I already have OpenClaw agents" → goes to agent creation
       with different intro text
   - Step 3: If template chosen → deploy, if manual → create first agent
   - Step 4: Redirect to setup flow (Phase 11)
2. Onboarding state: add onboarding_completed_at to teams table
   - If null, user sees onboarding wizard on login
   - Wizard can be skipped ("I'll set up later" link at bottom of each step)
   - Skip sets onboarding_completed_at and goes to empty dashboard

## Acceptance Criteria
- All 4 templates seeded with correct agent configs
- Template gallery shows templates with cost estimates
- Template detail view shows all agents with roles and models
- "Deploy" creates all agents, tokens, project, and assignments
- Onboarding wizard: new user sees it on first login
- All three paths work: template, manual, existing agents
- Wizard can be skipped
- Onboarding state persisted — returning users go straight to dashboard 