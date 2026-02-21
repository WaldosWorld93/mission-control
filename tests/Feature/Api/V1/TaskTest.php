<?php

use App\Enums\AttemptStatus;
use App\Enums\TaskPriority;
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

// --- INDEX ---

it('lists tasks excluding blocked', function (): void {
    Task::factory()->create(['team_id' => $this->team->id, 'project_id' => $this->project->id]);
    Task::factory()->blocked()->create(['team_id' => $this->team->id, 'project_id' => $this->project->id]);

    $this->getJson('/api/v1/tasks', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

it('filters tasks by project_id', function (): void {
    $otherProject = Project::factory()->create(['team_id' => $this->team->id]);
    Task::factory()->create(['team_id' => $this->team->id, 'project_id' => $this->project->id]);
    Task::factory()->create(['team_id' => $this->team->id, 'project_id' => $otherProject->id]);

    $this->getJson('/api/v1/tasks?project_id='.$this->project->id, agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

it('filters tasks by status', function (): void {
    Task::factory()->create(['team_id' => $this->team->id, 'project_id' => $this->project->id]);
    Task::factory()->done()->create(['team_id' => $this->team->id, 'project_id' => $this->project->id]);

    $this->getJson('/api/v1/tasks?status=done', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

it('rejects blocked as a filter status', function (): void {
    $this->getJson('/api/v1/tasks?status=blocked', agentHeaders($this->token))
        ->assertStatus(422);
});

it('filters tasks assigned to me', function (): void {
    Task::factory()->assignedTo($this->agent)->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);
    Task::factory()->create(['team_id' => $this->team->id, 'project_id' => $this->project->id]);

    $this->getJson('/api/v1/tasks?assigned_to=me', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

// --- STORE ---

it('creates a task with defaults', function (): void {
    $this->postJson('/api/v1/tasks', [
        'title' => 'Build the feature',
        'project_id' => $this->project->id,
    ], agentHeaders($this->token))
        ->assertStatus(201)
        ->assertJsonPath('data.title', 'Build the feature')
        ->assertJsonPath('data.status', 'backlog')
        ->assertJsonPath('data.priority', 'medium')
        ->assertJsonPath('data.created_by_agent_id', $this->agent->id);
});

it('creates a task with assigned agent', function (): void {
    $otherAgent = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'worker-1',
    ]);

    $this->postJson('/api/v1/tasks', [
        'title' => 'Delegated task',
        'project_id' => $this->project->id,
        'assigned_agent_name' => 'worker-1',
    ], agentHeaders($this->token))
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'assigned')
        ->assertJsonPath('data.assigned_agent_id', $otherAgent->id);
});

it('creates a subtask', function (): void {
    $parent = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'depth' => 0,
    ]);

    $this->postJson('/api/v1/tasks', [
        'title' => 'Subtask',
        'project_id' => $this->project->id,
        'parent_task_id' => $parent->id,
    ], agentHeaders($this->token))
        ->assertStatus(201)
        ->assertJsonPath('data.depth', 1)
        ->assertJsonPath('data.parent_task_id', $parent->id);
});

it('rejects subtask depth greater than 2', function (): void {
    $parent = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'depth' => 2,
    ]);

    $this->postJson('/api/v1/tasks', [
        'title' => 'Too deep',
        'project_id' => $this->project->id,
        'parent_task_id' => $parent->id,
    ], agentHeaders($this->token))
        ->assertStatus(422);
});

it('requires title on create', function (): void {
    $this->postJson('/api/v1/tasks', [
        'project_id' => $this->project->id,
    ], agentHeaders($this->token))
        ->assertStatus(422)
        ->assertJsonValidationErrors('title');
});

it('requires project_id on create', function (): void {
    $this->postJson('/api/v1/tasks', [
        'title' => 'No project',
    ], agentHeaders($this->token))
        ->assertStatus(422)
        ->assertJsonValidationErrors('project_id');
});

it('creates task with tags', function (): void {
    $this->postJson('/api/v1/tasks', [
        'title' => 'Tagged task',
        'project_id' => $this->project->id,
        'tags' => ['frontend', 'urgent'],
    ], agentHeaders($this->token))
        ->assertStatus(201)
        ->assertJsonPath('data.tags', ['frontend', 'urgent']);
});

it('creates task with priority', function (): void {
    $this->postJson('/api/v1/tasks', [
        'title' => 'Critical bug',
        'project_id' => $this->project->id,
        'priority' => 'critical',
    ], agentHeaders($this->token))
        ->assertStatus(201)
        ->assertJsonPath('data.priority', 'critical');
});

it('creates an attempt when task is assigned on creation', function (): void {
    $otherAgent = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'worker-2',
    ]);

    $response = $this->postJson('/api/v1/tasks', [
        'title' => 'Assigned task',
        'project_id' => $this->project->id,
        'assigned_agent_name' => 'worker-2',
    ], agentHeaders($this->token));

    $taskId = $response->json('data.id');
    $task = Task::find($taskId);

    expect($task->attempts)->toHaveCount(1);
    expect($task->attempts->first()->attempt_number)->toBe(1);
    expect($task->attempts->first()->status)->toBe(AttemptStatus::Active);
});

// --- UPDATE ---

it('updates task status with valid transition', function (): void {
    $task = Task::factory()->assignedTo($this->agent)->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $this->putJson("/api/v1/tasks/{$task->id}", [
        'status' => 'in_progress',
    ], agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'in_progress');
});

it('rejects invalid status transition', function (): void {
    $task = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'status' => TaskStatus::Backlog,
    ]);

    $this->putJson("/api/v1/tasks/{$task->id}", [
        'status' => 'done',
    ], agentHeaders($this->token))
        ->assertStatus(422);
});

it('rejects transition from system-only blocked status', function (): void {
    $task = Task::factory()->blocked()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $this->putJson("/api/v1/tasks/{$task->id}", [
        'status' => 'backlog',
    ], agentHeaders($this->token))
        ->assertStatus(422)
        ->assertJsonFragment(['message' => "Cannot transition from 'blocked' — system-managed status."]);
});

it('sets started_at on first transition to in_progress', function (): void {
    $task = Task::factory()->assignedTo($this->agent)->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $this->putJson("/api/v1/tasks/{$task->id}", [
        'status' => 'in_progress',
    ], agentHeaders($this->token));

    expect($task->fresh()->started_at)->not->toBeNull();
});

it('sets completed_at when transitioning to done', function (): void {
    $task = Task::factory()->inProgress()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'assigned_agent_id' => $this->agent->id,
    ]);

    $this->putJson("/api/v1/tasks/{$task->id}", [
        'status' => 'done',
    ], agentHeaders($this->token));

    expect($task->fresh()->completed_at)->not->toBeNull();
});

it('ends active attempt when task completes', function (): void {
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

    $this->putJson("/api/v1/tasks/{$task->id}", [
        'status' => 'done',
    ], agentHeaders($this->token));

    expect($task->attempts()->first()->fresh()->status)->toBe(AttemptStatus::Completed);
});

it('updates task result', function (): void {
    $task = Task::factory()->inProgress()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'assigned_agent_id' => $this->agent->id,
    ]);

    $this->putJson("/api/v1/tasks/{$task->id}", [
        'result' => 'All tests passing.',
        'status' => 'done',
    ], agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonPath('data.result', 'All tests passing.');
});

it('updates assigned agent by name', function (): void {
    $otherAgent = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'reassign-target',
    ]);

    $task = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $this->putJson("/api/v1/tasks/{$task->id}", [
        'assigned_agent_name' => 'reassign-target',
    ], agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonPath('data.assigned_agent_id', $otherAgent->id);
});

it('rejects update with unknown agent name', function (): void {
    $task = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $this->putJson("/api/v1/tasks/{$task->id}", [
        'assigned_agent_name' => 'nonexistent',
    ], agentHeaders($this->token))
        ->assertStatus(422);
});

it('enforces team isolation on tasks', function (): void {
    $otherTeam = createTeam();
    $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
    $otherTask = Task::factory()->create([
        'team_id' => $otherTeam->id,
        'project_id' => $otherProject->id,
    ]);

    // Reset team context to original team
    app(\App\Services\TeamContext::class)->set($this->team);

    $this->putJson("/api/v1/tasks/{$otherTask->id}", [
        'status' => 'assigned',
    ], agentHeaders($this->token))
        ->assertStatus(404);
});
