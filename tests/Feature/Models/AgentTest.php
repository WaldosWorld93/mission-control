<?php

use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\Heartbeat;
use App\Models\Message;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttempt;

beforeEach(function () {
    $this->team = createTeam();
});

it('creates an agent with factory defaults', function () {
    $agent = Agent::factory()->create(['team_id' => $this->team->id]);

    expect($agent)
        ->name->not->toBeEmpty()
        ->status->toBe(AgentStatus::Offline)
        ->is_lead->toBeFalse()
        ->is_paused->toBeFalse()
        ->consecutive_errors->toBe(0);
});

it('belongs to a team', function () {
    $agent = Agent::factory()->create(['team_id' => $this->team->id]);

    expect($agent->team->id)->toBe($this->team->id);
});

it('has many projects via pivot', function () {
    $agent = Agent::factory()->create(['team_id' => $this->team->id]);
    $projects = Project::factory(2)->create(['team_id' => $this->team->id]);
    $agent->projects()->attach($projects->pluck('id'), ['joined_at' => now()]);

    expect($agent->projects)->toHaveCount(2);
});

it('has many assigned tasks', function () {
    $agent = Agent::factory()->create(['team_id' => $this->team->id]);
    $project = Project::factory()->create(['team_id' => $this->team->id]);
    Task::factory(3)->create([
        'team_id' => $this->team->id,
        'project_id' => $project->id,
        'assigned_agent_id' => $agent->id,
    ]);

    expect($agent->assignedTasks)->toHaveCount(3);
});

it('has many created tasks', function () {
    $agent = Agent::factory()->create(['team_id' => $this->team->id]);
    $project = Project::factory()->create(['team_id' => $this->team->id]);
    Task::factory(2)->createdByAgent($agent)->create([
        'team_id' => $this->team->id,
        'project_id' => $project->id,
    ]);

    expect($agent->createdTasks)->toHaveCount(2);
});

it('has many messages', function () {
    $agent = Agent::factory()->create(['team_id' => $this->team->id]);
    Message::factory(2)->fromAgent($agent)->create(['team_id' => $this->team->id]);

    expect($agent->messages)->toHaveCount(2);
});

it('has many heartbeats', function () {
    $agent = Agent::factory()->create(['team_id' => $this->team->id]);
    Heartbeat::create([
        'agent_id' => $agent->id,
        'team_id' => $this->team->id,
        'status_reported' => 'idle',
        'created_at' => now(),
    ]);

    expect($agent->heartbeats)->toHaveCount(1);
});

it('has many task attempts', function () {
    $agent = Agent::factory()->create(['team_id' => $this->team->id]);
    $project = Project::factory()->create(['team_id' => $this->team->id]);
    $task = Task::factory()->create(['team_id' => $this->team->id, 'project_id' => $project->id]);
    TaskAttempt::factory()->create(['task_id' => $task->id, 'agent_id' => $agent->id]);

    expect($agent->taskAttempts)->toHaveCount(1);
});

it('scopes to online agents', function () {
    Agent::factory()->online()->create(['team_id' => $this->team->id]);
    Agent::factory()->offline()->create(['team_id' => $this->team->id]);

    expect(Agent::online()->count())->toBe(1);
});

it('scopes to paused agents', function () {
    Agent::factory()->paused()->create(['team_id' => $this->team->id]);
    Agent::factory()->create(['team_id' => $this->team->id]);

    expect(Agent::paused()->count())->toBe(1);
});

it('scopes to active (non-paused) agents', function () {
    Agent::factory()->paused()->create(['team_id' => $this->team->id]);
    Agent::factory()->create(['team_id' => $this->team->id]);

    expect(Agent::active()->count())->toBe(1);
});

it('scopes to lead agents', function () {
    Agent::factory()->lead()->create(['team_id' => $this->team->id]);
    Agent::factory()->create(['team_id' => $this->team->id]);

    expect(Agent::lead()->count())->toBe(1);
});

it('casts status to enum', function () {
    $agent = Agent::factory()->online()->create(['team_id' => $this->team->id]);

    expect($agent->status)->toBe(AgentStatus::Online);
});

it('casts skills and metadata to arrays', function () {
    $agent = Agent::factory()->create([
        'team_id' => $this->team->id,
        'skills' => ['writing', 'research'],
        'metadata' => ['version' => '1.0'],
    ]);

    expect($agent->skills)->toBe(['writing', 'research'])
        ->and($agent->metadata)->toBe(['version' => '1.0']);
});

it('stores soul_md and soul_hash', function () {
    $agent = Agent::factory()->withSoul('# My Agent')->create(['team_id' => $this->team->id]);

    expect($agent->soul_md)->toBe('# My Agent')
        ->and($agent->soul_hash)->toBe(hash('sha256', '# My Agent'));
});
