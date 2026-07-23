<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a user who still has to reset their password or set up two-factor
 * authentication from doing anything else, until they've done both — used
 * for accounts a site admin creates (or resets the password of), which start
 * with a password only the admin knows.
 */
class EnsureAccountSetupIsComplete
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! $user->mustCompleteAccountSetup()) {
            return $next($request);
        }

        if ($this->isExempt($request)) {
            return $next($request);
        }

        return redirect()->route('security.edit');
    }

    private function isExempt(Request $request): bool
    {
        return $request->routeIs('security.edit')
            || $request->routeIs('password.confirm*')
            || $request->routeIs('two-factor.*')
            || $request->routeIs('passkey.*')
            || $request->routeIs('logout')
            || $request->routeIs('*.logout')
            || $request->is('livewire-*/update');
    }
}
