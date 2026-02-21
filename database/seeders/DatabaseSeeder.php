<?php

namespace Database\Seeders;

use App\Enums\AgentStatus;
use App\Enums\DependencyType;
use App\Enums\MessageType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Agent;
use App\Models\Heartbeat;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskArtifact;
use App\Models\TaskAttempt;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Users ---
        $owner = User::factory()->create([
            'name' => 'Josh Richmond',
            'email' => 'josh@example.com',
        ]);

        $member = User::factory()->create([
            'name' => 'Sarah Chen',
            'email' => 'sarah@example.com',
        ]);

        // --- Team ---
        $team = Team::factory()->create([
            'name' => 'OpenClaw HQ',
            'slug' => 'openclaw-hq',
            'owner_id' => $owner->id,
        ]);

        $owner->update(['current_team_id' => $team->id]);
        $member->update(['current_team_id' => $team->id]);

        $team->users()->attach($owner->id, ['role' => 'owner']);
        $team->users()->attach($member->id, ['role' => 'member']);

        app(TeamContext::class)->set($team);

        // --- Agents (5) ---
        $lead = Agent::factory()->lead()->online()->create([
            'team_id' => $team->id,
            'name' => 'Jarvis',
            'role' => 'Lead Orchestrator',
            'description' => 'Coordinates all agents, breaks down work, reviews output.',
            'heartbeat_model' => 'anthropic/claude-haiku-4-5',
            'work_model' => 'anthropic/claude-opus-4-6',
        ]);

        $developer = Agent::factory()->online()->create([
            'team_id' => $team->id,
            'name' => 'Shuri',
            'role' => 'Full-Stack Developer',
            'description' => 'Implements features, writes code, builds APIs.',
            'skills' => ['php', 'laravel', 'javascript', 'vue'],
            'heartbeat_model' => 'anthropic/claude-haiku-4-5',
            'work_model' => 'anthropic/claude-sonnet-4-5',
        ]);

        $writer = Agent::factory()->idle()->create([
            'team_id' => $team->id,
            'name' => 'Hemingway',
            'role' => 'Content Writer',
            'description' => 'Drafts blog posts, documentation, and copy.',
            'skills' => ['writing', 'markdown', 'seo'],
        ]);

        $qa = Agent::factory()->online()->create([
            'team_id' => $team->id,
            'name' => 'Hawkeye',
            'role' => 'QA & Testing',
            'description' => 'Writes tests, reviews PRs, finds bugs.',
            'skills' => ['testing', 'pest', 'phpunit', 'code-review'],
        ]);

        $researcher = Agent::factory()->offline()->create([
            'team_id' => $team->id,
            'name' => 'Oracle',
            'role' => 'Researcher',
            'description' => 'Deep research, competitive analysis, data gathering.',
            'skills' => ['research', 'analysis', 'web-browsing'],
        ]);

        $agents = collect([$lead, $developer, $writer, $qa, $researcher]);

        // --- Projects (2) ---
        $prodProject = Project::factory()->create([
            'team_id' => $team->id,
            'name' => 'Product Redesign',
            'slug' => 'product-redesign',
            'description' => 'Complete redesign of the customer-facing dashboard with new component library.',
            'lead_agent_id' => $lead->id,
            'color' => '#6366f1',
            'started_at' => now()->subDays(5),
            'sort_order' => 0,
        ]);

        $blogProject = Project::factory()->create([
            'team_id' => $team->id,
            'name' => 'Q1 Blog Series',
            'slug' => 'q1-blog-series',
            'description' => 'Five-part blog series on AI agent coordination patterns.',
            'lead_agent_id' => $lead->id,
            'color' => '#f59e0b',
            'started_at' => now()->subDays(3),
            'sort_order' => 1,
        ]);

        // Assign agents to projects
        $prodProject->agents()->attach([
            $lead->id => ['role_override' => null, 'joined_at' => now()->subDays(5)],
            $developer->id => ['role_override' => null, 'joined_at' => now()->subDays(5)],
            $qa->id => ['role_override' => null, 'joined_at' => now()->subDays(5)],
        ]);

        $blogProject->agents()->attach([
            $lead->id => ['role_override' => null, 'joined_at' => now()->subDays(3)],
            $writer->id => ['role_override' => null, 'joined_at' => now()->subDays(3)],
            $researcher->id => ['role_override' => 'Research Lead', 'joined_at' => now()->subDays(3)],
        ]);

        // --- Product Redesign: Tasks with dependencies & subtasks ---
        $parentTask = Task::factory()->create([
            'team_id' => $team->id,
            'project_id' => $prodProject->id,
            'title' => 'Build User Authentication',
            'description' => 'Full auth system: login, registration, password reset, session management.',
            'status' => TaskStatus::InProgress,
            'priority' => TaskPriority::Critical,
            'assigned_agent_id' => $lead->id,
            'created_by_user_id' => $owner->id,
            'started_at' => now()->subDays(3),
        ]);

        $migration = Task::factory()->done()->subtaskOf($parentTask)->create([
            'title' => 'Create users migration',
            'assigned_agent_id' => $developer->id,
            'created_by_agent_id' => $lead->id,
            'result' => 'Created migration with email, password, remember_token columns. All tests pass.',
            'completed_at' => now()->subDays(2),
        ]);

        $loginEndpoint = Task::factory()->inProgress()->subtaskOf($parentTask)->create([
            'title' => 'Build login endpoint',
            'assigned_agent_id' => $developer->id,
            'created_by_agent_id' => $lead->id,
            'started_at' => now()->subDay(),
        ]);

        $registrationEndpoint = Task::factory()->assigned()->subtaskOf($parentTask)->create([
            'title' => 'Build registration endpoint',
            'assigned_agent_id' => $developer->id,
            'created_by_agent_id' => $lead->id,
            'claimed_at' => now()->subHours(6),
        ]);

        $passwordReset = Task::factory()->blocked()->subtaskOf($parentTask)->create([
            'title' => 'Build password reset flow',
            'assigned_agent_id' => $developer->id,
            'created_by_agent_id' => $lead->id,
        ]);

        $authTests = Task::factory()->blocked()->subtaskOf($parentTask)->create([
            'title' => 'Write auth test suite',
            'assigned_agent_id' => $qa->id,
            'created_by_agent_id' => $lead->id,
        ]);

        // Dependencies: login & registration depend on migration
        $loginEndpoint->dependencies()->attach($migration->id, [
            'dependency_type' => DependencyType::FinishToStart->value,
            'created_at' => now()->subDays(3),
        ]);
        $registrationEndpoint->dependencies()->attach($migration->id, [
            'dependency_type' => DependencyType::FinishToStart->value,
            'created_at' => now()->subDays(3),
        ]);

        // Password reset depends on login
        $passwordReset->dependencies()->attach($loginEndpoint->id, [
            'dependency_type' => DependencyType::FinishToStart->value,
            'created_at' => now()->subDays(3),
        ]);

        // Auth tests depend on login + registration
        $authTests->dependencies()->attach($loginEndpoint->id, [
            'dependency_type' => DependencyType::FinishToReview->value,
            'created_at' => now()->subDays(3),
        ]);
        $authTests->dependencies()->attach($registrationEndpoint->id, [
            'dependency_type' => DependencyType::FinishToReview->value,
            'created_at' => now()->subDays(3),
        ]);

        // Standalone product task
        Task::factory()->create([
            'team_id' => $team->id,
            'project_id' => $prodProject->id,
            'title' => 'Design new component library',
            'description' => 'Create reusable Blade/Vue components for the redesigned dashboard.',
            'status' => TaskStatus::Backlog,
            'priority' => TaskPriority::High,
            'created_by_user_id' => $owner->id,
            'tags' => ['frontend', 'design-system'],
        ]);

        // --- Q1 Blog Series: Tasks ---
        $researchTask = Task::factory()->done()->create([
            'team_id' => $team->id,
            'project_id' => $blogProject->id,
            'title' => 'Research AI agent coordination patterns',
            'description' => 'Survey existing approaches: AutoGen, CrewAI, LangGraph, custom solutions.',
            'assigned_agent_id' => $researcher->id,
            'created_by_agent_id' => $lead->id,
            'result' => 'Completed research. Found 5 main patterns: centralized orchestrator, peer-to-peer, hierarchical, blackboard, and market-based. Detailed notes attached as artifact.',
            'completed_at' => now()->subDay(),
        ]);

        $draftTask = Task::factory()->inReview()->create([
            'team_id' => $team->id,
            'project_id' => $blogProject->id,
            'title' => 'Draft blog post: AI Agent Coordination',
            'description' => 'Write a 1500-word post covering the coordination patterns from research.',
            'priority' => TaskPriority::High,
            'assigned_agent_id' => $writer->id,
            'created_by_agent_id' => $lead->id,
        ]);

        // Draft depends on research
        $draftTask->dependencies()->attach($researchTask->id, [
            'dependency_type' => DependencyType::FinishToStart->value,
            'created_at' => now()->subDays(2),
        ]);

        Task::factory()->create([
            'team_id' => $team->id,
            'project_id' => $blogProject->id,
            'title' => 'Draft blog post: Setting Up Your First Squad',
            'description' => 'Practical walkthrough of configuring a multi-agent team with Mission Control.',
            'status' => TaskStatus::Backlog,
            'priority' => TaskPriority::Medium,
            'created_by_agent_id' => $lead->id,
        ]);

        // --- Task Attempts ---
        TaskAttempt::factory()->completed()->create([
            'task_id' => $migration->id,
            'agent_id' => $developer->id,
            'attempt_number' => 1,
            'result' => 'Created migration successfully.',
            'started_at' => now()->subDays(2)->subHours(2),
            'ended_at' => now()->subDays(2),
        ]);

        TaskAttempt::factory()->create([
            'task_id' => $loginEndpoint->id,
            'agent_id' => $developer->id,
            'attempt_number' => 1,
            'started_at' => now()->subDay(),
            'status' => 'active',
        ]);

        // --- Task Artifacts ---
        TaskArtifact::factory()->withContent("# AI Agent Coordination Patterns\n\n## 1. Centralized Orchestrator\nA single lead agent delegates and coordinates...\n\n## 2. Peer-to-Peer\nAgents communicate directly without a coordinator...")->create([
            'task_id' => $researchTask->id,
            'team_id' => $team->id,
            'filename' => 'coordination-patterns-research.md',
            'display_name' => 'Research Notes',
            'uploaded_by_agent_id' => $researcher->id,
        ]);

        TaskArtifact::factory()->withContent("# AI Agent Coordination: 5 Patterns That Actually Work\n\nIf you've tried running multiple AI agents, you know the chaos that ensues...")->create([
            'task_id' => $draftTask->id,
            'team_id' => $team->id,
            'filename' => 'blog-post-draft.md',
            'display_name' => 'First Draft',
            'version' => 1,
            'uploaded_by_agent_id' => $writer->id,
        ]);

        TaskArtifact::factory()->withContent("# AI Agent Coordination: 5 Patterns That Actually Work\n\nRunning multiple AI agents without coordination is like herding cats...")->create([
            'task_id' => $draftTask->id,
            'team_id' => $team->id,
            'filename' => 'blog-post-draft.md',
            'display_name' => 'Revised Draft',
            'version' => 2,
            'uploaded_by_agent_id' => $writer->id,
        ]);

        // --- Message Thread linked to the draft task ---
        $thread = MessageThread::factory()->create([
            'team_id' => $team->id,
            'project_id' => $blogProject->id,
            'task_id' => $draftTask->id,
            'subject' => 'Blog post: AI Agent Coordination',
            'started_by_agent_id' => $lead->id,
            'message_count' => 4,
        ]);

        Message::factory()->create([
            'team_id' => $team->id,
            'project_id' => $blogProject->id,
            'from_agent_id' => $lead->id,
            'thread_id' => $thread->id,
            'sequence_in_thread' => 1,
            'content' => '@Hemingway please draft the blog post about AI agent coordination. Target 1500 words, focus on practical setup. Use the research notes from @Oracle.',
            'mentions' => [$writer->id, $researcher->id],
            'message_type' => MessageType::Chat,
        ]);

        Message::factory()->create([
            'team_id' => $team->id,
            'project_id' => $blogProject->id,
            'from_agent_id' => $writer->id,
            'thread_id' => $thread->id,
            'sequence_in_thread' => 2,
            'content' => '@Jarvis done — first draft is uploaded as an artifact. 1480 words. @Hawkeye can you review for technical accuracy?',
            'mentions' => [$lead->id, $qa->id],
            'message_type' => MessageType::Chat,
        ]);

        Message::factory()->create([
            'team_id' => $team->id,
            'project_id' => $blogProject->id,
            'from_agent_id' => $qa->id,
            'thread_id' => $thread->id,
            'sequence_in_thread' => 3,
            'content' => '@Hemingway found 3 issues — the intro paragraph needs reworking, the CTA is missing, and there\'s a broken link in section 2.',
            'mentions' => [$writer->id],
            'message_type' => MessageType::ReviewRequest,
            'read_by' => [$writer->id],
        ]);

        Message::factory()->create([
            'team_id' => $team->id,
            'project_id' => $blogProject->id,
            'from_agent_id' => $writer->id,
            'thread_id' => $thread->id,
            'sequence_in_thread' => 4,
            'content' => '@Hawkeye fixed all 3 issues. Revised draft uploaded (v2). Ready for final review.',
            'mentions' => [$qa->id],
            'message_type' => MessageType::Chat,
        ]);

        // --- Standalone team-wide thread ---
        $standupThread = MessageThread::factory()->create([
            'team_id' => $team->id,
            'subject' => 'Daily Standup — Feb 20',
            'started_by_agent_id' => $lead->id,
            'message_count' => 2,
        ]);

        Message::factory()->create([
            'team_id' => $team->id,
            'from_agent_id' => $lead->id,
            'thread_id' => $standupThread->id,
            'sequence_in_thread' => 1,
            'content' => 'Good morning team. Quick standup — what\'s everyone working on today?',
            'message_type' => MessageType::Standup,
        ]);

        Message::factory()->create([
            'team_id' => $team->id,
            'from_agent_id' => $developer->id,
            'thread_id' => $standupThread->id,
            'sequence_in_thread' => 2,
            'content' => 'Finishing up the login endpoint. Should be in review by end of day. Registration is next.',
            'message_type' => MessageType::Standup,
        ]);

        // --- Heartbeats (recent) ---
        foreach ($agents as $agent) {
            if ($agent->status !== AgentStatus::Offline) {
                for ($i = 3; $i >= 0; $i--) {
                    Heartbeat::create([
                        'agent_id' => $agent->id,
                        'team_id' => $team->id,
                        'status_reported' => $agent->status->value,
                        'soul_hash_reported' => $agent->soul_hash,
                        'ip_address' => '127.0.0.1',
                        'metadata' => ['openclaw_version' => '1.2.0'],
                        'created_at' => now()->subMinutes($i * 3),
                    ]);
                }
            }
        }

        $this->command->info('Seeded: 1 team, 2 users, 5 agents, 2 projects, 10 tasks (with dependencies & subtasks), 2 threads with 6 messages, 3 artifacts, heartbeats.');
    }
}
