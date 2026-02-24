<?php

use App\Models\Team;
use App\Models\User;

it('shows landing page for guests', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSeeText('Mission Control')
        ->assertSeeText('Command your AI agents from one dashboard.')
        ->assertSee('/login')
        ->assertSee('/register');
});

it('shows all landing page sections for guests', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSeeText('Everything your squad needs')
        ->assertSeeText('Agent Heartbeats')
        ->assertSeeText('Task Dependencies')
        ->assertSeeText('Threaded Conversations')
        ->assertSeeText('Up and running in 3 steps')
        ->assertSeeText('Simple pricing');
});

it('shows get started buttons for guests', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSeeText('Get started')
        ->assertSeeText('Sign in');
});

it('shows dashboard link for logged-in users', function () {
    $team = Team::factory()->create(['onboarding_completed_at' => now()]);
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => 'owner']);

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect('/home');
});
