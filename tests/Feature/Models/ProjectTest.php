<?php

use App\Enums\ProjectStatus;
use App\Models\Agent;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Project;
use App\Models\Task;

beforeEach(function () {
    $this->team = createTeam();
});

it('creates a project with factory defaults', function () {
    $project = Project::factory()->create(['team_id' => $this->team->id]);

    expect($project)
        ->name->not->toBeEmpty()
        ->slug->not->toBeEmpty()
        ->status->toBe(ProjectStatus::Active);
});

it('belongs to a team', function () {
    $project = Project::factory()->create(['team_id' => $this->team->id]);

    expect($project->team->id)->toBe($this->team->id);
});

it('has a lead agent', function () {
    $agent = Agent::factory()->lead()->create(['team_id' => $this->team->id]);
    $project = Project::factory()->create([
        'team_id' => $this->team->id,
        'lead_agent_id' => $agent->id,
    ]);

    expect($project->leadAgent->id)->toBe($agent->id);
});

it('has many agents via pivot', function () {
    $project = Project::factory()->create(['team_id' => $this->team->id]);
    $agents = Agent::factory(3)->create(['team_id' => $this->team->id]);
    $project->agents()->attach($agents->pluck('id'), ['joined_at' => now()]);

    expect($project->agents)->toHaveCount(3);
});

it('stores role_override on pivot', function () {
    $project = Project::factory()->create(['team_id' => $this->team->id]);
    $agent = Agent::factory()->create(['team_id' => $this->team->id]);
    $project->agents()->attach($agent->id, [
        'role_override' => 'QA Lead',
        'joined_at' => now(),
    ]);

    expect($project->agents->first()->pivot->role_override)->toBe('QA Lead');
});

it('has many tasks', function () {
    $project = Project::factory()->create(['team_id' => $this->team->id]);
    Task::factory(5)->create(['team_id' => $this->team->id, 'project_id' => $project->id]);

    expect($project->tasks)->toHaveCount(5);
});

it('has many messages', function () {
    $project = Project::factory()->create(['team_id' => $this->team->id]);
    Message::factory(2)->create(['team_id' => $this->team->id, 'project_id' => $project->id]);

    expect($project->messages)->toHaveCount(2);
});

it('has many message threads', function () {
    $project = Project::factory()->create(['team_id' => $this->team->id]);
    MessageThread::factory(2)->create(['team_id' => $this->team->id, 'project_id' => $project->id]);

    expect($project->messageThreads)->toHaveCount(2);
});

it('casts status to enum', function () {
    $project = Project::factory()->completed()->create(['team_id' => $this->team->id]);

    expect($project->status)->toBe(ProjectStatus::Completed)
        ->and($project->completed_at)->not->toBeNull();
});

it('casts settings to array', function () {
    $project = Project::factory()->create([
        'team_id' => $this->team->id,
        'settings' => ['default_priority' => 'high'],
    ]);

    expect($project->settings)->toBe(['default_priority' => 'high']);
});
