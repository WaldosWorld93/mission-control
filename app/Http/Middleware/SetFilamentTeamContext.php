<?php

namespace App\Http\Middleware;

use App\Services\TeamContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetFilamentTeamContext
{
    public function __construct(protected TeamContext $teamContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->currentTeam) {
            $this->teamContext->set($user->currentTeam);
        }

        return $next($request);
    }
}
