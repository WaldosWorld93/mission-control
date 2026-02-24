<?php

use App\Models\Agent;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->team = Team::factory()->create(['onboarding_completed_at' => now()]);
    $this->user = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->users()->attach($this->user, ['role' => 'owner']);
    app(\App\Services\TeamContext::class)->set($this->team);
});

it('renders the squad checklist page', function () {
    $this->actingAs($this->user)
        ->get('/setup/squad')
        ->assertSuccessful()
        ->assertSeeText('Squad Setup');
});

it('is accessible during onboarding', function () {
    $this->team->update(['onboarding_completed_at' => null]);

    $this->actingAs($this->user)
        ->get('/setup/squad')
        ->assertSuccessful();
});

it('shows empty state when no agents exist', function () {
    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\SquadChecklistWidget::class)
        ->assertSeeText('No agents');
});

it('lists all agents in the team', function () {
    Agent::factory()->create(['team_id' => $this->team->id, 'name' => 'Alpha']);
    Agent::factory()->create(['team_id' => $this->team->id, 'name' => 'Bravo']);

    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\SquadChecklistWidget::class)
        ->assertSeeText('Alpha')
        ->assertSeeText('Bravo');
});

it('shows correct count of connected agents', function () {
    Agent::factory()->online()->create(['team_id' => $this->team->id, 'name' => 'Connected Agent']);
    Agent::factory()->create(['team_id' => $this->team->id, 'name' => 'Waiting Agent']);

    $this->actingAs($this->user);

    $component = Livewire::test(\App\Livewire\SquadChecklistWidget::class);

    $component->assertSeeText('Connected Agent')
        ->assertSeeText('Waiting Agent');
});

it('shows waiting status for agents without heartbeat', function () {
    Agent::factory()->create(['team_id' => $this->team->id, 'name' => 'New Agent']);

    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\SquadChecklistWidget::class)
        ->assertSeeText('Waiting');
});

it('shows connected status for agents with heartbeat', function () {
    Agent::factory()->online()->create(['team_id' => $this->team->id, 'name' => 'Active Agent']);

    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\SquadChecklistWidget::class)
        ->assertSeeText('Connected');
});

it('shows all connected message when every agent has heartbeated', function () {
    Agent::factory()->online()->create(['team_id' => $this->team->id]);
    Agent::factory()->online()->create(['team_id' => $this->team->id]);

    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\SquadChecklistWidget::class)
        ->assertSeeText('All agents connected');
});

it('does not show all connected when some agents are waiting', function () {
    Agent::factory()->online()->create(['team_id' => $this->team->id]);
    Agent::factory()->create(['team_id' => $this->team->id]);

    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\SquadChecklistWidget::class)
        ->assertDontSeeText('All agents connected');
});

it('includes setup links for each agent', function () {
    $agent = Agent::factory()->create(['team_id' => $this->team->id]);

    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\SquadChecklistWidget::class)
        ->assertSee("/agents/{$agent->id}/setup");
});
