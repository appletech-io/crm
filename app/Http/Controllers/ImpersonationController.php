<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Companies\CompanyResource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function stop(): RedirectResponse
    {
        $impersonatorId = session('impersonator_id');

        abort_unless($impersonatorId, 403);

        // loginUsingId() re-queries via the auth provider, which is subject to
        // the impersonated user's BelongsToCompany scope and would silently
        // fail to find an impersonator from a different company.
        $impersonator = User::withoutGlobalScope('company')->findOrFail($impersonatorId);

        Auth::login($impersonator);
        session()->forget('impersonator_id');

        return redirect(CompanyResource::getUrl('index'));
    }
}
