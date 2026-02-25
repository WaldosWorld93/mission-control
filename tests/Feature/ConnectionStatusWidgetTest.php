<?php

use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->team = Team::factory()->create(['onboarding_completed_at' => now()]);
    $this->user = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->users()->attach($this->user, ['role' => 'owner']);
    app(\App\Services\TeamContext::class)->set($this->team);

    $this->agent = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Scout',
    ]);
});

it('shows waiting state for agent without heartbeat', function () {
    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\ConnectionStatusWidget::class, ['agent' => $this->agent])
        ->assertSet('state', 'waiting')
        ->assertSeeText('Waiting for first heartbeat');
});

it('shows connected state for agent with existing heartbeat', function () {
    $this->agent->update([
        'last_heartbeat_at' => now(),
        'status' => AgentStatus::Idle,
    ]);

    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\ConnectionStatusWidget::class, ['agent' => $this->agent])
        ->assertSet('state', 'connected')
        ->assertSet('agentStatus', 'idle');
});

it('transitions to connected on heartbeat event', function () {
    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\ConnectionStatusWidget::class, ['agent' => $this->agent])
        ->assertSet('state', 'waiting')
        ->call('onHeartbeat', [
            'agentId' => $this->agent->id,
            'status' => 'idle',
        ])
        ->assertSet('state', 'connected')
        ->assertSet('agentStatus', 'idle');
});

it('transitions to error state on error heartbeat', function () {
    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\ConnectionStatusWidget::class, ['agent' => $this->agent])
        ->call('onHeartbeat', [
            'agentId' => $this->agent->id,
            'status' => 'error',
        ])
        ->assertSet('state', 'error')
        ->assertSet('agentStatus', 'error');
});

it('ignores heartbeat events for other agents', function () {
    $otherAgent = Agent::factory()->create(['team_id' => $this->team->id]);

    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\ConnectionStatusWidget::class, ['agent' => $this->agent])
        ->call('onHeartbeat', [
            'agentId' => $otherAgent->id,
            'status' => 'idle',
        ])
        ->assertSet('state', 'waiting');
});

it('sets connected at time on heartbeat', function () {
    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\ConnectionStatusWidget::class, ['agent' => $this->agent])
        ->call('onHeartbeat', [
            'agentId' => $this->agent->id,
            'status' => 'idle',
        ])
        ->assertSet('connectedAt', fn ($value) => $value !== null);
});

it('displays agent name in waiting message', function () {
    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\ConnectionStatusWidget::class, ['agent' => $this->agent])
        ->assertSeeText('Scout');
});

it('transitions to connected via polling when heartbeat is received', function () {
    $this->actingAs($this->user);

    $component = Livewire::test(\App\Livewire\ConnectionStatusWidget::class, ['agent' => $this->agent])
        ->assertSet('state', 'waiting');

    // Simulate a heartbeat arriving in the database
    $this->agent->update([
        'last_heartbeat_at' => now(),
        'status' => AgentStatus::Online,
    ]);

    $component->call('checkHeartbeat')
        ->assertSet('state', 'connected')
        ->assertSet('agentStatus', 'online');
});

it('does not update state when no new heartbeat on poll', function () {
    $this->agent->update([
        'last_heartbeat_at' => now()->subMinutes(5),
        'status' => AgentStatus::Idle,
    ]);

    $this->actingAs($this->user);

    $component = Livewire::test(\App\Livewire\ConnectionStatusWidget::class, ['agent' => $this->agent])
        ->assertSet('state', 'connected')
        ->assertSet('agentStatus', 'idle');

    // Calling checkHeartbeat without a new heartbeat should not change anything
    $component->call('checkHeartbeat')
        ->assertSet('state', 'connected')
        ->assertSet('agentStatus', 'idle');
});

it('detects error status via polling', function () {
    $this->actingAs($this->user);

    $component = Livewire::test(\App\Livewire\ConnectionStatusWidget::class, ['agent' => $this->agent])
        ->assertSet('state', 'waiting');

    $this->agent->update([
        'last_heartbeat_at' => now(),
        'status' => AgentStatus::Error,
    ]);

    $component->call('checkHeartbeat')
        ->assertSet('state', 'error')
        ->assertSet('agentStatus', 'error');
});

it('shows next agent setup button when squad has unconfigured agents', function () {
    $this->agent->update([
        'last_heartbeat_at' => now(),
        'status' => AgentStatus::Idle,
    ]);

    $nextAgent = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Data Analyst',
    ]);

    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\ConnectionStatusWidget::class, ['agent' => $this->agent])
        ->assertSet('state', 'connected')
        ->assertSee('Set up Data Analyst')
        ->assertSee("agents/{$nextAgent->id}/setup")
        ->assertSee('Remaining:');
});

it('shows all agents connected when squad is fully configured', function () {
    $this->agent->update([
        'last_heartbeat_at' => now(),
        'status' => AgentStatus::Idle,
    ]);

    Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Data Analyst',
        'last_heartbeat_at' => now(),
        'status' => AgentStatus::Idle,
    ]);

    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\ConnectionStatusWidget::class, ['agent' => $this->agent])
        ->assertSet('state', 'connected')
        ->assertSee('All agents connected! Go to Dashboard')
        ->assertDontSee('Remaining:');
});

it('shows simple dashboard button for single agent', function () {
    $this->agent->update([
        'last_heartbeat_at' => now(),
        'status' => AgentStatus::Idle,
    ]);

    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\ConnectionStatusWidget::class, ['agent' => $this->agent])
        ->assertSet('state', 'connected')
        ->assertSee('Go to Dashboard')
        ->assertDontSee('Remaining:')
        ->assertDontSee('All agents connected');
});

it('prioritizes lead agent as next agent when lead is not connected', function () {
    $this->agent->update([
        'last_heartbeat_at' => now(),
        'status' => AgentStatus::Idle,
    ]);

    // Create a non-lead agent (alphabetically first)
    Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Alpha Worker',
    ]);

    // Create the lead agent (alphabetically last but should be prioritized)
    $lead = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Zeta Commander',
        'is_lead' => true,
    ]);

    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\ConnectionStatusWidget::class, ['agent' => $this->agent])
        ->assertSee('Set up Zeta Commander')
        ->assertSee("agents/{$lead->id}/setup");
});

it('shows non-lead agent as next when lead is already connected', function () {
    $this->agent->update([
        'last_heartbeat_at' => now(),
        'status' => AgentStatus::Idle,
    ]);

    Agent::factory()->online()->create([
        'team_id' => $this->team->id,
        'name' => 'Commander',
        'is_lead' => true,
    ]);

    $worker = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Worker',
    ]);

    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\ConnectionStatusWidget::class, ['agent' => $this->agent])
        ->assertSee('Set up Worker')
        ->assertSee("agents/{$worker->id}/setup");
});
