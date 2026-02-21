<?php

use App\Models\Agent;
use App\Models\MessageThread;
use App\Models\Project;
use App\Models\Task;

beforeEach(function (): void {
    $this->team = createTeam();
    [$this->agent, $this->token] = createAgentWithToken($this->team);
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->agent->projects()->attach($this->project);
});

// --- INDEX ---

it('lists threads for agent projects', function (): void {
    MessageThread::factory()->count(2)->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $this->getJson('/api/v1/threads', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

it('excludes threads from projects agent is not attached to', function (): void {
    $otherProject = Project::factory()->create(['team_id' => $this->team->id]);

    MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);
    MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $otherProject->id,
    ]);

    $this->getJson('/api/v1/threads', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

it('filters threads by is_resolved true', function (): void {
    MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'is_resolved' => false,
    ]);
    MessageThread::factory()->resolved()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $this->getJson('/api/v1/threads?is_resolved=1', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_resolved', true);
});

it('filters threads by is_resolved false', function (): void {
    MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'is_resolved' => false,
    ]);
    MessageThread::factory()->resolved()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $this->getJson('/api/v1/threads?is_resolved=0', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_resolved', false);
});

it('filters threads by task_id', function (): void {
    $task = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'task_id' => $task->id,
    ]);
    MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $this->getJson("/api/v1/threads?task_id={$task->id}", agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

it('includes started_by agent info', function (): void {
    MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'started_by_agent_id' => $this->agent->id,
    ]);

    $this->getJson('/api/v1/threads', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonPath('data.0.started_by_agent.id', $this->agent->id);
});

// --- UPDATE ---

it('marks a thread as resolved', function (): void {
    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'is_resolved' => false,
    ]);

    $this->patchJson("/api/v1/threads/{$thread->id}", [
        'is_resolved' => true,
    ], agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonPath('data.is_resolved', true);

    expect($thread->fresh()->is_resolved)->toBeTrue();
});

it('marks a thread as unresolved', function (): void {
    $thread = MessageThread::factory()->resolved()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $this->patchJson("/api/v1/threads/{$thread->id}", [
        'is_resolved' => false,
    ], agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonPath('data.is_resolved', false);
});

it('requires is_resolved on update', function (): void {
    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $this->patchJson("/api/v1/threads/{$thread->id}", [], agentHeaders($this->token))
        ->assertStatus(422)
        ->assertJsonValidationErrors('is_resolved');
});

it('enforces team isolation on thread update', function (): void {
    $otherTeam = createTeam();
    $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
    $otherThread = MessageThread::factory()->create([
        'team_id' => $otherTeam->id,
        'project_id' => $otherProject->id,
    ]);

    // Reset team context to original team
    app(\App\Services\TeamContext::class)->set($this->team);

    $this->patchJson("/api/v1/threads/{$otherThread->id}", [
        'is_resolved' => true,
    ], agentHeaders($this->token))
        ->assertStatus(404);
});

it('returns threads without project_id when no project filter', function (): void {
    // Threads with null project should not appear (agent projects filter)
    MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => null,
    ]);

    $this->getJson('/api/v1/threads', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
});
