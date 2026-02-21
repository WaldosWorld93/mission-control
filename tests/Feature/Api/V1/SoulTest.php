<?php

beforeEach(function (): void {
    $this->team = createTeam();
    [$this->agent, $this->token] = createAgentWithToken($this->team);
});

it('returns soul_md and soul_hash', function (): void {
    $this->agent->update([
        'soul_md' => '# Agent Soul',
        'soul_hash' => hash('sha256', '# Agent Soul'),
    ]);

    $this->getJson('/api/v1/soul', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonPath('data.soul_md', '# Agent Soul')
        ->assertJsonPath('data.soul_hash', hash('sha256', '# Agent Soul'));
});

it('returns null when agent has no soul', function (): void {
    $this->getJson('/api/v1/soul', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonPath('data.soul_md', null)
        ->assertJsonPath('data.soul_hash', null);
});
