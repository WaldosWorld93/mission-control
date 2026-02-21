<?php

use App\Enums\DependencyType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Agent;
use App\Models\MessageThread;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskArtifact;
use App\Models\TaskAttempt;
use App\Models\User;

beforeEach(function () {
    $this->team = createTeam();
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
});

it('creates a task with factory defaults', function () {
    $task = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    expect($task)
        ->status->toBe(TaskStatus::Backlog)
        ->priority->toBe(TaskPriority::Medium)
        ->depth->toBe(0);
});

it('belongs to a project', function () {
    $task = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    expect($task->project->id)->toBe($this->project->id);
});

it('belongs to an assigned agent', function () {
    $agent = Agent::factory()->create(['team_id' => $this->team->id]);
    $task = Task::factory()->assignedTo($agent)->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    expect($task->assignedAgent->id)->toBe($agent->id);
});

it('tracks who created it (agent)', function () {
    $agent = Agent::factory()->create(['team_id' => $this->team->id]);
    $task = Task::factory()->createdByAgent($agent)->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    expect($task->createdByAgent->id)->toBe($agent->id);
});

it('tracks who created it (user)', function () {
    $user = User::factory()->create();
    $task = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'created_by_user_id' => $user->id,
    ]);

    expect($task->createdByUser->id)->toBe($user->id);
});

it('supports parent/subtask relationships', function () {
    $parent = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);
    $child1 = Task::factory()->subtaskOf($parent)->create();
    $child2 = Task::factory()->subtaskOf($parent)->create();

    expect($parent->subtasks)->toHaveCount(2)
        ->and($child1->parent->id)->toBe($parent->id)
        ->and($child1->depth)->toBe(1);
});

it('supports nested subtasks up to depth 2', function () {
    $parent = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'depth' => 0,
    ]);
    $child = Task::factory()->subtaskOf($parent)->create();
    $grandchild = Task::factory()->subtaskOf($child)->create();

    expect($grandchild->depth)->toBe(2)
        ->and($grandchild->parent->id)->toBe($child->id)
        ->and($child->parent->id)->toBe($parent->id);
});

it('has dependencies (prerequisites)', function () {
    $prerequisite = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);
    $dependent = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $dependent->dependencies()->attach($prerequisite->id, [
        'dependency_type' => DependencyType::FinishToStart->value,
        'created_at' => now(),
    ]);

    expect($dependent->dependencies)->toHaveCount(1)
        ->and($dependent->dependencies->first()->id)->toBe($prerequisite->id)
        ->and($dependent->dependencies->first()->pivot->dependency_type)->toBe(DependencyType::FinishToStart->value);
});

it('has dependents (tasks that depend on this)', function () {
    $prerequisite = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);
    $dependent1 = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);
    $dependent2 = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $dependent1->dependencies()->attach($prerequisite->id, [
        'dependency_type' => DependencyType::FinishToStart->value,
        'created_at' => now(),
    ]);
    $dependent2->dependencies()->attach($prerequisite->id, [
        'dependency_type' => DependencyType::FinishToReview->value,
        'created_at' => now(),
    ]);

    expect($prerequisite->dependents)->toHaveCount(2);
});

it('has many attempts', function () {
    $agent = Agent::factory()->create(['team_id' => $this->team->id]);
    $task = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);
    TaskAttempt::factory()->create(['task_id' => $task->id, 'agent_id' => $agent->id, 'attempt_number' => 1]);
    TaskAttempt::factory()->create(['task_id' => $task->id, 'agent_id' => $agent->id, 'attempt_number' => 2]);

    expect($task->attempts)->toHaveCount(2);
});

it('has many artifacts', function () {
    $task = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);
    TaskArtifact::factory(2)->create(['task_id' => $task->id, 'team_id' => $this->team->id]);

    expect($task->artifacts)->toHaveCount(2);
});

it('has a linked thread', function () {
    $task = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);
    MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'task_id' => $task->id,
    ]);

    expect($task->thread)->not->toBeNull()
        ->and($task->thread->task_id)->toBe($task->id);
});

it('casts tags to array', function () {
    $task = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'tags' => ['frontend', 'urgent'],
    ]);

    expect($task->tags)->toBe(['frontend', 'urgent']);
});

it('casts datetime columns', function () {
    $task = Task::factory()->done()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'due_at' => '2026-03-01 12:00:00',
    ]);

    expect($task->completed_at)->toBeInstanceOf(\Carbon\Carbon::class)
        ->and($task->due_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('uses factory states for priorities', function () {
    $critical = Task::factory()->critical()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);
    $low = Task::factory()->low()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    expect($critical->priority)->toBe(TaskPriority::Critical)
        ->and($low->priority)->toBe(TaskPriority::Low);
});
