<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfOnboardingIncomplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $team = $user->currentTeam;

        if (! $team || $team->onboarding_completed_at !== null) {
            return $next($request);
        }

        $onboardingPaths = ['onboarding', 'templates', 'templates/deployed'];
        $currentPath = trim($request->path(), '/');

        if (in_array($currentPath, $onboardingPaths)) {
            return $next($request);
        }

        // Allow Filament asset and Livewire requests
        if ($request->is('livewire/*') || $request->is('filament/*')) {
            return $next($request);
        }

        return redirect(url('onboarding'));
    }
}
