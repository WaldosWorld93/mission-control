<?php

namespace App\Listeners;

use App\Models\Team;
use Filament\Events\Auth\Registered;
use Illuminate\Support\Str;

class CreatePersonalTeam
{
    public function handle(Registered $event): void
    {
        $user = $event->getUser();

        $team = Team::create([
            'name' => $user->name."'s Team",
            'slug' => Str::slug($user->name).'-'.Str::random(4),
            'owner_id' => $user->id,
        ]);

        $team->users()->attach($user->id, ['role' => 'owner']);

        $user->update(['current_team_id' => $team->id]);
    }
}
