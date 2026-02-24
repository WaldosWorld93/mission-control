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

        $currentPath = trim($request->path(), '/');

        // Allow onboarding-related paths
        if (in_array($currentPath, ['onboarding', 'templates', 'templates/deployed', 'setup/squad'])) {
            return $next($request);
        }

        // Allow agent setup pages (agents/{id}/setup)
        if (preg_match('#^agents/[^/]+/setup$#', $currentPath)) {
            return $next($request);
        }

        // Allow agent resource pages (for manual path)
        if (str_starts_with($currentPath, 'agents/')) {
            return $next($request);
        }

        // Allow Filament asset and Livewire requests
        if ($request->is('livewire/*') || $request->is('filament/*')) {
            return $next($request);
        }

        return redirect(url('onboarding'));
    }
}
