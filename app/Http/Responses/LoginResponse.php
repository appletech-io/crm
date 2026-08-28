<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        /** @var Request $request */
        // A user forced to set up their account (EnsureAccountSetupIsComplete
        // redirects them straight to security.edit) just proved their
        // identity by typing this same temporary password to log in —
        // asking them to confirm it again on Fortify's password.confirm
        // screen, seconds later, before they even reach the "current
        // password" field on the change-password form itself, is pure
        // repetition. Pre-satisfying RequirePassword's own session check
        // here skips that screen for this one forced flow only; anyone
        // visiting security settings later (once their session's
        // confirmation window has lapsed) still gets the genuine prompt.
        if ($request->user()?->mustCompleteAccountSetup()) {
            $request->session()->put('auth.password_confirmed_at', time());
        }

        $redirectTo = match (true) {
            $request->user()?->hasRole('candidate') => '/candidate',
            $request->user()?->hasRole('client') => '/client',
            default => Fortify::redirects('login'),
        };

        return redirect()->intended($redirectTo);
    }
}
