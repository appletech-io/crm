<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Fortify;

/**
 * A password-free "log in as" shortcut shown on the login screen only when
 * APP_ENV=demo (see resources/views/livewire/auth/login.blade.php and the
 * route registration in routes/web.php) — lets anyone with access to the
 * demo site pick a company and one of its users (staff or portal account)
 * and land straight in as them, for quickly showing off different roles
 * without needing each user's real password. Hard-gated here too, not just
 * at the route, since a route-cache or future route-registration change
 * should never be the only thing standing between this and production.
 */
class DemoQuickLoginController extends Controller
{
    public function login(Request $request): RedirectResponse
    {
        abort_unless(app()->environment(['demo', 'DEMO', 'Demo']), 403);

        // Not route-model-bound: this runs pre-auth, and the target user can
        // belong to any company, so the lookup deliberately bypasses
        // BelongsToCompany's scope the same way ImpersonationController's
        // does when restoring the original impersonator.
        $user = User::withoutGlobalScope('company')->findOrFail($request->integer('user_id'));

        Auth::login($user);

        // AuthenticateSession stores a password hash tied to whoever was
        // logged in; forgetting it here forces it to recompute for the new
        // user on the next request instead of comparing against a stale
        // hash and forcing an immediate logout — same reasoning as
        // CompaniesTable's own "View As" action.
        session()->forget('password_hash_web');

        // Mirrors LoginResponse's own pre-satisfaction of the same check —
        // a demo quick-login is standing in for having just typed a
        // password, so a forced-setup account shouldn't immediately hit
        // Fortify's separate password-confirmation screen straight after.
        if ($user->mustCompleteAccountSetup()) {
            request()->session()->put('auth.password_confirmed_at', time());
        }

        $redirectTo = match (true) {
            $user->hasRole('candidate') => '/candidate',
            $user->hasRole('client') => '/client',
            default => Fortify::redirects('login'),
        };

        return redirect($redirectTo);
    }
}
