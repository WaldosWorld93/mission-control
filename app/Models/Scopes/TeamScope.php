<?php

namespace App\Models\Scopes;

use App\Services\TeamContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TeamScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $teamContext = app(TeamContext::class);

        if ($teamId = $teamContext->id()) {
            $builder->where($model->getTable().'.team_id', $teamId);
        }
    }
}
