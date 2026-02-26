<?php

use App\Enums\AgentStatus;
use App\Enums\AttemptStatus;
use App\Models\Agent;
use App\Models\Project;
use App\Models\Task;

beforeEach(function (): void {
    $this->team = createTeam();
    [$this->agent, $this->token] = createAgentWithToken($this->team);
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->agent->projects()->attach($this->project);
});

it('returns the correct response structure', function (): void {
    $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
    ], agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'notifications',
            'tasks',
            'blocked_summary' => ['count', 'next_up'],
            'soul_sync',
            'config' => ['heartbeat_interval_seconds', 'active_projects'],
        ]);
});

it('never returns blocked tasks in tasks array', function (): void {
    Task::factory()->blocked()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'assigned_agent_id' => $this->agent->id,
    ]);

    Task::factory()->assignedTo($this->agent)->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $response = $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
    ], agentHeaders($this->token));

    $response->assertStatus(200);
    expect($response->json('tasks'))->toHaveCount(1);
});

it('includes blocked_summary with count and next_up', function (): void {
    $dep = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'title' => 'Blocker task',
    ]);

    $blocked = Task::factory()->blocked()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'assigned_agent_id' => $this->agent->id,
        'title' => 'Blocked task',
    ]);

    $blocked->dependencies()->attach($dep->id, [
        'dependency_type' => 'finish_to_start',
    ]);

    $response = $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
    ], agentHeaders($this->token));

    expect($response->json('blocked_summary.count'))->toBe(1);
    expect($response->json('blocked_summary.next_up'))->toHaveCount(1);
    expect($response->json('blocked_summary.next_up.0.title'))->toBe('Blocked task');
    expect($response->json('blocked_summary.next_up.0.waiting_on'))->toContain('Blocker task');
});

it('returns soul_sync when hash mismatches', function (): void {
    $this->agent->update([
        'soul_md' => '# My Soul',
        'soul_hash' => hash('sha256', '# My Soul'),
    ]);

    $response = $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
        'soul_hash' => 'wrong_hash_value_that_is_exactly_sixtyfour_characters_long_pad00',
    ], agentHeaders($this->token));

    expect($response->json('soul_sync'))->not->toBeNull();
    expect($response->json('soul_sync.soul_md'))->toBe('# My Soul');
});

it('returns null soul_sync when hash matches', function (): void {
    $soulHash = hash('sha256', '# My Soul');
    $this->agent->update([
        'soul_md' => '# My Soul',
        'soul_hash' => $soulHash,
    ]);

    $response = $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
        'soul_hash' => $soulHash,
    ], agentHeaders($this->token));

    expect($response->json('soul_sync'))->toBeNull();
});

it('updates agent status and last_heartbeat_at', function (): void {
    $this->postJson('/api/v1/heartbeat', [
        'status' => 'busy',
    ], agentHeaders($this->token));

    $this->agent->refresh();
    expect($this->agent->status)->toBe(AgentStatus::Busy);
    expect($this->agent->last_heartbeat_at)->not->toBeNull();
});

it('increments consecutive_errors on error', function (): void {
    $this->postJson('/api/v1/heartbeat', [
        'status' => 'error',
        'error' => [
            'type' => 'runtime',
            'message' => 'Something went wrong',
        ],
    ], agentHeaders($this->token));

    expect($this->agent->fresh()->consecutive_errors)->toBe(1);
});

it('resets consecutive_errors on successful heartbeat', function (): void {
    $this->agent->update(['consecutive_errors' => 2]);

    $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
    ], agentHeaders($this->token));

    expect($this->agent->fresh()->consecutive_errors)->toBe(0);
});

it('triggers circuit breaker after 3 consecutive errors', function (): void {
    $this->agent->update(['consecutive_errors' => 2]);

    $response = $this->postJson('/api/v1/heartbeat', [
        'status' => 'error',
        'error' => [
            'type' => 'runtime',
            'message' => 'Third failure',
        ],
    ], agentHeaders($this->token));

    $response->assertJson([
        'status' => 'paused',
        'reason' => 'circuit_breaker',
    ]);

    $this->agent->refresh();
    expect($this->agent->is_paused)->toBeTrue();
    expect($this->agent->paused_reason)->toBe('circuit_breaker');
});

it('handles task failure in error report', function (): void {
    $task = Task::factory()->inProgress()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'assigned_agent_id' => $this->agent->id,
    ]);

    $task->attempts()->create([
        'agent_id' => $this->agent->id,
        'attempt_number' => 1,
        'started_at' => now(),
        'status' => AttemptStatus::Active,
    ]);

    $this->postJson('/api/v1/heartbeat', [
        'status' => 'error',
        'error' => [
            'type' => 'task_failure',
            'task_id' => $task->id,
            'message' => 'Build failed',
        ],
    ], agentHeaders($this->token));

    $attempt = $task->attempts()->first();
    expect($attempt->fresh()->status)->toBe(AttemptStatus::Failed);
    expect($attempt->fresh()->error_message)->toBe('Build failed');
});

it('uses adaptive heartbeat interval - 120s with tasks', function (): void {
    Task::factory()->assignedTo($this->agent)->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $response = $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
    ], agentHeaders($this->token));

    expect($response->json('config.heartbeat_interval_seconds'))->toBe(120);
});

it('uses adaptive heartbeat interval - 300s when idle', function (): void {
    $response = $this->postJson('/api/v1/heartbeat', [
        'status' => 'idle',
    ], agentHeaders($this->token));

    expect($response->json('config.heartbeat_interval_seconds'))->toBe(300);
});

it('includes active projects in config', function (): void {
    $response = $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
    ], agentHeaders($this->token));

    expect($response->json('config.active_projects'))->toContain($this->project->name);
});

it('logs heartbeat record', function (): void {
    $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
    ], agentHeaders($this->token));

    expect($this->agent->heartbeats()->count())->toBe(1);
});

it('returns correct soul_sync for each agent in multi-agent team', function (): void {
    // Set up the first agent (from beforeEach) with soul
    $this->agent->update([
        'soul_md' => '# Research Lead Soul',
        'soul_hash' => hash('sha256', '# Research Lead Soul'),
    ]);

    // Create a second agent with different soul
    [$agent2, $token2] = createAgentWithToken($this->team);
    $agent2->update([
        'soul_md' => '# Primary Researcher Soul',
        'soul_hash' => hash('sha256', '# Primary Researcher Soul'),
    ]);

    $staleHash = str_repeat('a', 64);

    // Agent 1 heartbeat should return Agent 1's soul
    $response1 = $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
        'soul_hash' => $staleHash,
    ], agentHeaders($this->token));

    expect($response1->json('soul_sync.soul_md'))->toBe('# Research Lead Soul');

    // Agent 2 heartbeat should return Agent 2's soul
    $response2 = $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
        'soul_hash' => $staleHash,
    ], agentHeaders($token2));

    expect($response2->json('soul_sync.soul_md'))->toBe('# Primary Researcher Soul');
});

it('validates soul_hash must be 64 characters', function (): void {
    $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
        'soul_hash' => 'too_short',
    ], agentHeaders($this->token))
        ->assertStatus(422)
        ->assertJsonValidationErrors('soul_hash');
});
