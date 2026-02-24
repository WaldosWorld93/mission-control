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
