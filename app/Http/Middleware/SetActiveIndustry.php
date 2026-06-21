<?php

namespace App\Http\Middleware;

use App\Models\Industry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class SetActiveIndustry
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // @TODO refactor this to use a service class instead of doing all the logic in the middleware
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (
            $request->routeIs('sector.select') ||
            $request->routeIs('default-livewire.update') ||
            $request->is('livewire-*/update')
        ) {
            return $next($request);
        }

        $slugCacheKey = "user.{$user->id}.active_industry";
        $idCacheKey   = "user.{$user->id}.active_industry_id";

        $slug = Cache::get($slugCacheKey);
        $id   = Cache::get($idCacheKey);

        // Both already cached — just set on request and move on
        if ($slug && $id) {
            $request->attributes->set('active_industry', $slug);
            $request->attributes->set('active_industry_id', $id);

            return $next($request);
        }

        // Slug cached but ID missing — derive ID from existing slug, don't recalculate
        if ($slug && ! $id) {
            $id = Industry::where('slug', $slug)->value('id');
            Cache::put($idCacheKey, $id, now()->addHour());

            $request->attributes->set('active_industry', $slug);
            $request->attributes->set('active_industry_id', $id);

            return $next($request);
        }

        // ID cached but slug missing — derive slug from existing ID, don't recalculate
        if ($id && ! $slug) {
            $slug = Industry::where('id', $id)->value('slug');
            Cache::put($slugCacheKey, $slug, now()->addHour());

            $request->attributes->set('active_industry', $slug);
            $request->attributes->set('active_industry_id', $id);

            return $next($request);
        }

        // Nothing cached — calculate from scratch
        $userIndustries = $user->industries()->get();

        if ($userIndustries->count() > 1) {
            return redirect()->route('sector.select');
        }

        if (! $user->company_id || $userIndustries->isEmpty()) {
            return $next($request);
        }

        $industrySlug = $userIndustries->first()->slug;

        $exists = $user->company->industries()->where('slug', $industrySlug)->exists();

        if (! $exists) {
            return $next($request);
        }

        $slug = $industrySlug;
        $id   = Industry::where('slug', $slug)->value('id');

        Cache::put($slugCacheKey, $slug, now()->addHour());
        Cache::put($idCacheKey, $id, now()->addHour());

        $request->attributes->set('active_industry', $slug);
        $request->attributes->set('active_industry_id', $id);

        return $next($request);
    }
}
