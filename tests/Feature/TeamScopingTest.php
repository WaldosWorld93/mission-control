<?php

use App\Models\Agent;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskArtifact;
use App\Models\Team;
use App\Services\TeamContext;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->teamA = Team::factory()->create();
    $this->teamB = Team::factory()->create();

    // Create data for team A
    $agentA = Agent::factory()->create(['team_id' => $this->teamA->id]);
    $projectA = Project::factory()->create(['team_id' => $this->teamA->id]);
    $taskA = Task::factory()->create(['team_id' => $this->teamA->id, 'project_id' => $projectA->id]);
    MessageThread::factory()->create(['team_id' => $this->teamA->id]);
    Message::factory()->create(['team_id' => $this->teamA->id]);
    TaskArtifact::factory()->create(['team_id' => $this->teamA->id, 'task_id' => $taskA->id]);

    // Create data for team B
    $agentB = Agent::factory()->create(['team_id' => $this->teamB->id]);
    $projectB = Project::factory()->create(['team_id' => $this->teamB->id]);
    $taskB = Task::factory()->create(['team_id' => $this->teamB->id, 'project_id' => $projectB->id]);
    MessageThread::factory()->create(['team_id' => $this->teamB->id]);
    Message::factory()->create(['team_id' => $this->teamB->id]);
    TaskArtifact::factory()->create(['team_id' => $this->teamB->id, 'task_id' => $taskB->id]);
});

it('scopes agents to the current team', function () {
    app(TeamContext::class)->set($this->teamA);

    expect(Agent::count())->toBe(1)
        ->and(Agent::first()->team_id)->toBe($this->teamA->id);
});

it('scopes projects to the current team', function () {
    app(TeamContext::class)->set($this->teamA);

    expect(Project::count())->toBe(1)
        ->and(Project::first()->team_id)->toBe($this->teamA->id);
});

it('scopes tasks to the current team', function () {
    app(TeamContext::class)->set($this->teamB);

    expect(Task::count())->toBe(1)
        ->and(Task::first()->team_id)->toBe($this->teamB->id);
});

it('scopes message threads to the current team', function () {
    app(TeamContext::class)->set($this->teamA);

    expect(MessageThread::count())->toBe(1);
});

it('scopes messages to the current team', function () {
    app(TeamContext::class)->set($this->teamB);

    expect(Message::count())->toBe(1);
});

it('scopes task artifacts to the current team', function () {
    app(TeamContext::class)->set($this->teamA);

    expect(TaskArtifact::count())->toBe(1);
});

it('returns all records when no team context is set', function () {
    // Don't set any team context
    expect(Agent::count())->toBe(2)
        ->and(Project::count())->toBe(2)
        ->and(Task::count())->toBe(2);
});

it('can bypass team scope with withoutGlobalScopes', function () {
    app(TeamContext::class)->set($this->teamA);

    expect(Agent::count())->toBe(1)
        ->and(Agent::withoutGlobalScopes()->count())->toBe(2);
});

it('auto-sets team_id on creation when team context is set', function () {
    app(TeamContext::class)->set($this->teamA);

    $agent = Agent::factory()->create(['team_id' => null]);

    expect($agent->team_id)->toBe($this->teamA->id);
});
