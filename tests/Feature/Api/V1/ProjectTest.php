<?php

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Team;

beforeEach(function (): void {
    $this->team = createTeam();
    [$this->agent, $this->token] = createAgentWithToken($this->team);
});

it('returns only assigned projects', function (): void {
    $assigned = Project::factory()->create(['team_id' => $this->team->id]);
    Project::factory()->create(['team_id' => $this->team->id]); // unassigned

    $this->agent->projects()->attach($assigned);

    $this->getJson('/api/v1/projects', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $assigned->id);
});

it('returns only active projects', function (): void {
    $active = Project::factory()->create(['team_id' => $this->team->id]);
    $paused = Project::factory()->paused()->create(['team_id' => $this->team->id]);
    $completed = Project::factory()->completed()->create(['team_id' => $this->team->id]);
    $archived = Project::factory()->archived()->create(['team_id' => $this->team->id]);

    $this->agent->projects()->attach([$active->id, $paused->id, $completed->id, $archived->id]);

    $this->getJson('/api/v1/projects', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id);
});

it('enforces team isolation on projects', function (): void {
    $otherTeam = Team::factory()->create();
    $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);

    // Attach project from other team directly (bypassing scoping)
    $this->agent->projects()->attach($otherProject);

    // But the agent should only see projects within their team scope
    $this->getJson('/api/v1/projects', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

it('returns empty array when agent has no projects', function (): void {
    $this->getJson('/api/v1/projects', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
});
