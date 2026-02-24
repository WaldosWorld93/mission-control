<?php

use App\Models\Team;
use App\Models\User;

it('redirects to landing page after logout', function () {
    $team = Team::factory()->create(['onboarding_completed_at' => now()]);
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => 'owner']);

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect('/');
});
