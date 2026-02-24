<?php

use App\Models\Team;
use App\Models\User;

it('shows landing page for guests', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSeeText('Mission Control')
        ->assertSeeText('The coordination backbone for your AI agent squads.')
        ->assertSee('/login')
        ->assertSee('/register');
});

it('redirects logged-in users to home', function () {
    $team = Team::factory()->create(['onboarding_completed_at' => now()]);
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => 'owner']);

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect('/home');
});
