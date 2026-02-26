<?php

use App\Models\Agent;
use App\Models\Team;
use App\Services\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Create a team and set the TeamContext so global scopes work.
 */
function createTeam(array $attributes = []): Team
{
    $team = Team::factory()->create($attributes);
    app(TeamContext::class)->set($team);

    return $team;
}

/**
 * Create an agent with a known plaintext token. Returns [Agent, string $plainToken].
 *
 * @return array{0: Agent, 1: string}
 */
function createAgentWithToken(Team $team): array
{
    $agent = Agent::factory()->make(['team_id' => $team->id]);
    $plainToken = $agent->generateApiToken();
    $agent->save();

    return [$agent, $plainToken];
}

/**
 * Build authorization headers for agent API requests.
 *
 * @return array{Authorization: string, Accept: string}
 */
function agentHeaders(string $plainToken): array
{
    return [
        'Authorization' => 'Bearer '.$plainToken,
        'Accept' => 'application/json',
    ];
}
