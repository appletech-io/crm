<?php

namespace App\Http\Middleware;

use App\Actions\Users\GenerateDefaultQuickLinks;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after SetActiveIndustry (so active_industry() is already resolved)
 * and seeds a user's first set of quick links the first time they're seen.
 * Gated on quick_links_seeded_at rather than "do they currently have any",
 * so this only ever runs once per user — including if they go on to delete
 * every one of their quick links afterwards.
 */
class EnsureDefaultQuickLinksExist
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $request->is('livewire-*/update') && ! $user->quick_links_seeded_at) {
            // Only actually add the defaults if they don't already have any
            // quick links of their own — that can happen if some other
            // process (a seeder, an import) created one before this ever
            // ran. Either way, marking seeded_at now means this never runs
            // again, so it won't re-add defaults after they delete them all.
            if (! $user->quickLinks()->exists()) {
                foreach (GenerateDefaultQuickLinks::run() as $link) {
                    $user->quickLinks()->create($link);
                }
            }

            $user->quick_links_seeded_at = now();
            $user->save();
        }

        return $next($request);
    }
}
