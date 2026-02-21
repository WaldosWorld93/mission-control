<?php

use App\Enums\TeamPlan;
use App\Models\Agent;
use App\Models\Heartbeat;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;

it('creates a team with factory defaults', function () {
    $team = Team::factory()->create();

    expect($team)
        ->name->not->toBeEmpty()
        ->slug->not->toBeEmpty()
        ->plan->toBe(TeamPlan::Free);
});

it('belongs to an owner', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $user->id]);

    expect($team->owner->id)->toBe($user->id);
});

it('has many users via pivot', function () {
    $team = Team::factory()->create();
    $users = User::factory(3)->create();
    $team->users()->attach($users->pluck('id'), ['role' => 'member']);

    expect($team->users)->toHaveCount(3)
        ->each(fn ($user) => $user->pivot->role->toBe('member'));
});

it('has many agents', function () {
    $team = createTeam();
    Agent::factory(3)->create(['team_id' => $team->id]);

    expect($team->agents)->toHaveCount(3);
});

it('has many projects', function () {
    $team = createTeam();
    Project::factory(2)->create(['team_id' => $team->id]);

    expect($team->projects)->toHaveCount(2);
});

it('has many tasks', function () {
    $team = createTeam();
    $project = Project::factory()->create(['team_id' => $team->id]);
    Task::factory(4)->create(['team_id' => $team->id, 'project_id' => $project->id]);

    expect($team->tasks)->toHaveCount(4);
});

it('has many messages', function () {
    $team = createTeam();
    Message::factory(2)->create(['team_id' => $team->id]);

    expect($team->messages)->toHaveCount(2);
});

it('has many message threads', function () {
    $team = createTeam();
    MessageThread::factory(2)->create(['team_id' => $team->id]);

    expect($team->messageThreads)->toHaveCount(2);
});

it('has many heartbeats', function () {
    $team = createTeam();
    $agent = Agent::factory()->create(['team_id' => $team->id]);
    Heartbeat::create([
        'agent_id' => $agent->id,
        'team_id' => $team->id,
        'status_reported' => 'idle',
        'created_at' => now(),
    ]);

    expect($team->heartbeats)->toHaveCount(1);
});

it('casts plan to enum', function () {
    $team = Team::factory()->pro()->create();

    expect($team->plan)->toBe(TeamPlan::Pro);
});
