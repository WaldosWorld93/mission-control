<?php

use App\Enums\AttemptStatus;
use App\Enums\TaskStatus;
use App\Models\Agent;
use App\Models\Project;
use App\Models\Task;

beforeEach(function (): void {
    $this->team = createTeam();
    [$this->agent, $this->token] = createAgentWithToken($this->team);
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->agent->projects()->attach($this->project);
});

it('successfully claims a backlog task', function (): void {
    $task = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'status' => TaskStatus::Backlog,
    ]);

    $this->postJson("/api/v1/tasks/{$task->id}/claim", [], agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'assigned')
        ->assertJsonPath('data.assigned_agent_id', $this->agent->id);

    expect($task->fresh()->claimed_at)->not->toBeNull();
});

it('creates an attempt on claim', function (): void {
    $task = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'status' => TaskStatus::Backlog,
    ]);

    $this->postJson("/api/v1/tasks/{$task->id}/claim", [], agentHeaders($this->token));

    $task->refresh();
    expect($task->attempts)->toHaveCount(1);
    expect($task->attempts->first()->agent_id)->toBe($this->agent->id);
    expect($task->attempts->first()->attempt_number)->toBe(1);
    expect($task->attempts->first()->status)->toBe(AttemptStatus::Active);
});

it('increments attempt number on subsequent claims', function (): void {
    $task = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'status' => TaskStatus::Backlog,
    ]);

    // Simulate a previous attempt
    $task->attempts()->create([
        'agent_id' => $this->agent->id,
        'attempt_number' => 1,
        'started_at' => now()->subHour(),
        'ended_at' => now()->subMinutes(30),
        'status' => AttemptStatus::Failed,
    ]);

    $this->postJson("/api/v1/tasks/{$task->id}/claim", [], agentHeaders($this->token));

    $task->refresh();
    expect($task->attempts)->toHaveCount(2);
    expect($task->attempts->last()->attempt_number)->toBe(2);
});

it('returns 409 when claiming an already-claimed task', function (): void {
    $otherAgent = Agent::factory()->create(['team_id' => $this->team->id]);

    $task = Task::factory()->assignedTo($otherAgent)->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $this->postJson("/api/v1/tasks/{$task->id}/claim", [], agentHeaders($this->token))
        ->assertStatus(409)
        ->assertJsonFragment(['message' => 'Task is not available for claiming.']);
});

it('returns 409 when claiming a non-backlog task', function (): void {
    $task = Task::factory()->inProgress()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $this->postJson("/api/v1/tasks/{$task->id}/claim", [], agentHeaders($this->token))
        ->assertStatus(409);
});

it('returns 409 when claiming a blocked task', function (): void {
    $task = Task::factory()->blocked()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $this->postJson("/api/v1/tasks/{$task->id}/claim", [], agentHeaders($this->token))
        ->assertStatus(409);
});

it('handles concurrent claim attempts atomically', function (): void {
    $task = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'status' => TaskStatus::Backlog,
    ]);

    $agent2 = Agent::factory()->create(['team_id' => $this->team->id]);
    $token2 = str()->random(40);
    $agent2->update(['api_token' => hash('sha256', $token2)]);

    // First claim should succeed
    $this->postJson("/api/v1/tasks/{$task->id}/claim", [], agentHeaders($this->token))
        ->assertStatus(200);

    // Second claim should fail
    $this->postJson("/api/v1/tasks/{$task->id}/claim", [], agentHeaders($token2))
        ->assertStatus(409);
});
