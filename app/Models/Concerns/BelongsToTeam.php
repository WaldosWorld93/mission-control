<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TeamScope;
use App\Models\Team;
use App\Services\TeamContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTeam
{
    public static function bootBelongsToTeam(): void
    {
        static::addGlobalScope(new TeamScope);

        static::creating(function ($model) {
            if (! $model->team_id) {
                $model->team_id = app(TeamContext::class)->id()
                    ?? auth()->user()?->current_team_id;
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
