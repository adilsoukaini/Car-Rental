<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replaces Filament's default Authenticate middleware for the admin panel.
 *
 * Filament's default behaviour: abort(403) when canAccessPanel() returns false.
 * That surfaces a raw "Forbidden" page to logged-in customers who type /admin
 * into the URL bar. This middleware redirects them to the storefront home
 * page instead, which is both friendlier and reveals less about the admin
 * panel's structure.
 */
class EnsureAdminPanelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            throw new AuthenticationException('Unauthenticated.', [], Filament::getLoginUrl());
        }

        $user = $guard->user();
        $panel = Filament::getCurrentOrDefaultPanel();

        $canAccess = $user instanceof FilamentUser
            ? $user->canAccessPanel($panel)
            : config('app.env') === 'local';

        if (! $canAccess) {
            // Log the user out of the panel guard so they can't retry immediately
            // by reloading, and redirect to the storefront home page.
            $guard->logout();

            return redirect()->route('home')
                ->with('error', 'You do not have permission to access the admin panel.');
        }

        return $next($request);
    }
}
