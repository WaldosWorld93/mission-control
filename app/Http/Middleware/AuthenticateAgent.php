<?php

namespace App\Http\Middleware;

use App\Models\Agent;
use App\Services\TeamContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAgent
{
    public function __construct(private TeamContext $teamContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $hashedToken = hash('sha256', $token);

        $agent = Agent::withoutGlobalScopes()
            ->where('api_token', $hashedToken)
            ->first();

        if (! $agent) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($agent->is_paused) {
            return response()->json([
                'status' => 'paused',
                'reason' => $agent->paused_reason,
            ]);
        }

        $request->attributes->set('agent', $agent);
        app()->instance('agent', $agent);
        $this->teamContext->set($agent->team);

        return $next($request);
    }
}
