<?php

use App\Models\Team;
use App\Models\User;
use Filament\Events\Auth\Registered;

it('creates a personal team when a user registers', function () {
    $user = User::factory()->create();

    event(new Registered($user));

    $user->refresh();

    expect($user->current_team_id)->not->toBeNull();
    expect($user->currentTeam)->toBeInstanceOf(Team::class);
    expect($user->currentTeam->owner_id)->toBe($user->id);
    expect($user->currentTeam->name)->toContain($user->name);
    expect($user->teams)->toHaveCount(1);
    expect($user->teams->first()->pivot->role)->toBe('owner');
});

it('generates a unique slug for the team', function () {
    $user = User::factory()->create(['name' => 'Jane Doe']);

    event(new Registered($user));

    $team = $user->refresh()->currentTeam;

    expect($team->slug)->toStartWith('jane-doe-');
});
