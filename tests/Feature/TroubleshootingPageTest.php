<?php

use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->team = Team::factory()->create(['onboarding_completed_at' => now()]);
    $this->user = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->users()->attach($this->user, ['role' => 'owner']);
});

it('renders the troubleshooting page', function () {
    $this->actingAs($this->user)
        ->get('/troubleshooting')
        ->assertSuccessful()
        ->assertSeeText('Troubleshooting');
});

it('shows all troubleshooting sections', function () {
    $this->actingAs($this->user)
        ->get('/troubleshooting')
        ->assertSeeText('Agent Not Connecting')
        ->assertSeeText('Without Calling API')
        ->assertSeeText('SOUL.md Not Syncing')
        ->assertSeeText('Messages Not Delivering')
        ->assertSeeText('Tasks Stuck in Blocked Status')
        ->assertSeeText('High Token Costs')
        ->assertSeeText('Agent Paused by Circuit Breaker')
        ->assertSeeText('Artifact Upload Failing');
});

it('includes diagnostic curl commands', function () {
    $this->actingAs($this->user)
        ->get('/troubleshooting')
        ->assertSee('$MC_API_URL/heartbeat', false)
        ->assertSee('$MC_AGENT_TOKEN', false)
        ->assertSee('$MC_API_URL/soul', false)
        ->assertSee('$MC_API_URL/messages', false);
});

it('has actionable steps in each section', function () {
    $response = $this->actingAs($this->user)
        ->get('/troubleshooting');

    // Each section should have diagnostic or fix steps
    $response->assertSeeText('Step-by-Step Fix')
        ->assertSeeText('Common Causes')
        ->assertSeeText('Diagnose');
});
