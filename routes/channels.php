<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('team.{teamId}', function ($user, $teamId) {
    return $user->teams()->where('teams.id', $teamId)->exists();
});
