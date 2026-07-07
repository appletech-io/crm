<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectVettingCandidateToDocuments
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $candidate = $request->user()?->candidate;

        if (! $candidate || $request->is('candidate/documents')) {
            return $next($request);
        }

        if ($candidate->currentStatusName() === 'Vetting') {
            return redirect('/candidate/documents');
        }

        return $next($request);
    }
}
