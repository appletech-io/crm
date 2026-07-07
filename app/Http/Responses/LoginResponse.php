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
        $redirectTo = $request->user()?->hasRole('candidate')
            ? '/candidate'
            : Fortify::redirects('login');

        return redirect()->intended($redirectTo);
    }
}
