<?php

use App\Enums\AttemptStatus;
use App\Enums\DependencyType;
use App\Enums\TaskStatus;
use App\Jobs\ResolveDependencies;
use App\Models\Agent;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->team = createTeam();
    [$this->agent, $this->token] = createAgentWithToken($this->team);
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->agent->projects()->attach($this->project);
});

it('creates a task with unmet dependencies as blocked', function (): void {
    $dep = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $this->postJson('/api/v1/tasks', [
        'title' => 'Dependent task',
        'project_id' => $this->project->id,
        'depends_on' => [$dep->id],
    ], agentHeaders($this->token))
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'blocked');
});

it('creates a task with met dependencies as backlog', function (): void {
    $dep = Task::factory()->done()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $this->postJson('/api/v1/tasks', [
        'title' => 'Dependent task',
        'project_id' => $this->project->id,
        'depends_on' => [$dep->id],
    ], agentHeaders($this->token))
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'backlog');
});

it('creates a task with met dependencies and agent as assigned', function (): void {
    $dep = Task::factory()->done()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $assignee = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'dep-worker',
    ]);

    $this->postJson('/api/v1/tasks', [
        'title' => 'Dependent assigned',
        'project_id' => $this->project->id,
        'depends_on' => [$dep->id],
        'assigned_agent_name' => 'dep-worker',
    ], agentHeaders($this->token))
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'assigned');
});

it('rejects cross-project dependencies', function (): void {
    $otherProject = Project::factory()->create(['team_id' => $this->team->id]);
    $dep = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $otherProject->id,
    ]);

    $this->postJson('/api/v1/tasks', [
        'title' => 'Cross project dep',
        'project_id' => $this->project->id,
        'depends_on' => [$dep->id],
    ], agentHeaders($this->token))
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'Cross-project dependencies are not allowed.']);
});

it('dispatches ResolveDependencies job when task moves to done', function (): void {
    Queue::fake();

    $task = Task::factory()->inProgress()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'assigned_agent_id' => $this->agent->id,
    ]);

    $dependent = Task::factory()->blocked()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $dependent->dependencies()->attach($task->id, [
        'dependency_type' => DependencyType::FinishToStart->value,
    ]);

    $this->putJson("/api/v1/tasks/{$task->id}", [
        'status' => 'done',
    ], agentHeaders($this->token));

    Queue::assertPushed(ResolveDependencies::class, function ($job) use ($dependent) {
        return $job->dependentTaskId === $dependent->id;
    });
});

it('dispatches ResolveDependencies job when task moves to in_review', function (): void {
    Queue::fake();

    $task = Task::factory()->inProgress()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'assigned_agent_id' => $this->agent->id,
    ]);

    $dependent = Task::factory()->blocked()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $dependent->dependencies()->attach($task->id, [
        'dependency_type' => DependencyType::FinishToReview->value,
    ]);

    $this->putJson("/api/v1/tasks/{$task->id}", [
        'status' => 'in_review',
    ], agentHeaders($this->token));

    Queue::assertPushed(ResolveDependencies::class);
});

it('resolves blocked task to backlog when all finish_to_start deps are done', function (): void {
    $dep = Task::factory()->done()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $blocked = Task::factory()->blocked()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $blocked->dependencies()->attach($dep->id, [
        'dependency_type' => DependencyType::FinishToStart->value,
    ]);

    (new ResolveDependencies($blocked->id))->handle();

    expect($blocked->fresh()->status)->toBe(TaskStatus::Backlog);
});

it('resolves blocked task to assigned when agent is set', function (): void {
    $dep = Task::factory()->done()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $blocked = Task::factory()->blocked()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'assigned_agent_id' => $this->agent->id,
    ]);

    $blocked->dependencies()->attach($dep->id, [
        'dependency_type' => DependencyType::FinishToStart->value,
    ]);

    (new ResolveDependencies($blocked->id))->handle();

    $blocked->refresh();
    expect($blocked->status)->toBe(TaskStatus::Assigned);
    expect($blocked->attempts)->toHaveCount(1);
    expect($blocked->attempts->first()->status)->toBe(AttemptStatus::Active);
});

it('does not resolve when deps are not met', function (): void {
    $dep = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'status' => TaskStatus::InProgress,
    ]);

    $blocked = Task::factory()->blocked()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $blocked->dependencies()->attach($dep->id, [
        'dependency_type' => DependencyType::FinishToStart->value,
    ]);

    (new ResolveDependencies($blocked->id))->handle();

    expect($blocked->fresh()->status)->toBe(TaskStatus::Blocked);
});

it('resolves finish_to_review when dep is in_review', function (): void {
    $dep = Task::factory()->inReview()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $blocked = Task::factory()->blocked()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $blocked->dependencies()->attach($dep->id, [
        'dependency_type' => DependencyType::FinishToReview->value,
    ]);

    (new ResolveDependencies($blocked->id))->handle();

    expect($blocked->fresh()->status)->toBe(TaskStatus::Backlog);
});

it('resolves finish_to_review when dep is done', function (): void {
    $dep = Task::factory()->done()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $blocked = Task::factory()->blocked()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $blocked->dependencies()->attach($dep->id, [
        'dependency_type' => DependencyType::FinishToReview->value,
    ]);

    (new ResolveDependencies($blocked->id))->handle();

    expect($blocked->fresh()->status)->toBe(TaskStatus::Backlog);
});

it('does not resolve finish_to_review when dep is in_progress', function (): void {
    $dep = Task::factory()->inProgress()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $blocked = Task::factory()->blocked()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $blocked->dependencies()->attach($dep->id, [
        'dependency_type' => DependencyType::FinishToReview->value,
    ]);

    (new ResolveDependencies($blocked->id))->handle();

    expect($blocked->fresh()->status)->toBe(TaskStatus::Blocked);
});

it('auto-completes parent when all subtasks are done', function (): void {
    $parent = Task::factory()->inProgress()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'assigned_agent_id' => $this->agent->id,
    ]);

    Task::factory()->done()->subtaskOf($parent)->create();

    $sub2 = Task::factory()->inProgress()->subtaskOf($parent)->create([
        'assigned_agent_id' => $this->agent->id,
    ]);

    $this->putJson("/api/v1/tasks/{$sub2->id}", [
        'status' => 'done',
    ], agentHeaders($this->token));

    expect($parent->fresh()->status)->toBe(TaskStatus::Done);
    expect($parent->fresh()->completed_at)->not->toBeNull();
});

it('does not auto-complete parent when some subtasks are not done', function (): void {
    $parent = Task::factory()->inProgress()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'assigned_agent_id' => $this->agent->id,
    ]);

    Task::factory()->subtaskOf($parent)->create(['status' => TaskStatus::Backlog]);

    $sub2 = Task::factory()->inProgress()->subtaskOf($parent)->create([
        'assigned_agent_id' => $this->agent->id,
    ]);

    $this->putJson("/api/v1/tasks/{$sub2->id}", [
        'status' => 'done',
    ], agentHeaders($this->token));

    expect($parent->fresh()->status)->toBe(TaskStatus::InProgress);
});

it('handles multiple dependencies where all must be met', function (): void {
    $dep1 = Task::factory()->done()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $dep2 = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'status' => TaskStatus::InProgress,
    ]);

    $blocked = Task::factory()->blocked()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $blocked->dependencies()->attach($dep1->id, [
        'dependency_type' => DependencyType::FinishToStart->value,
    ]);
    $blocked->dependencies()->attach($dep2->id, [
        'dependency_type' => DependencyType::FinishToStart->value,
    ]);

    (new ResolveDependencies($blocked->id))->handle();

    expect($blocked->fresh()->status)->toBe(TaskStatus::Blocked);
});
