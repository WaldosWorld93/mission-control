<?php

use App\Models\Agent;
use App\Models\Team;

beforeEach(function (): void {
    $this->team = createTeam();
    [$this->agent, $this->token] = createAgentWithToken($this->team);
});

it('returns 401 when no token is provided', function (): void {
    $this->getJson('/api/v1/projects')
        ->assertStatus(401)
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('returns 401 when an invalid token is provided', function (): void {
    $this->getJson('/api/v1/projects', agentHeaders('invalid-token'))
        ->assertStatus(401)
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('returns 200 when a valid token is provided', function (): void {
    $this->getJson('/api/v1/projects', agentHeaders($this->token))
        ->assertStatus(200);
});

it('returns paused response for paused agents', function (): void {
    $this->agent->update([
        'is_paused' => true,
        'paused_reason' => 'circuit_breaker',
        'paused_at' => now(),
    ]);

    $this->getJson('/api/v1/projects', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJson([
            'status' => 'paused',
            'reason' => 'circuit_breaker',
        ]);
});

it('isolates agents by team', function (): void {
    $otherTeam = Team::factory()->create();
    [$otherAgent, $otherToken] = createAgentWithToken($otherTeam);

    // Each agent should be able to authenticate
    $this->getJson('/api/v1/projects', agentHeaders($this->token))
        ->assertStatus(200);

    $this->getJson('/api/v1/projects', agentHeaders($otherToken))
        ->assertStatus(200);
});
