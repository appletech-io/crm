<?php

namespace App\Actions\Users;

use App\Filament\Resources\UserQuickLinks\UserQuickLinkResource;
use App\Filament\Support\QuickLinkCatalog;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A starting set of up to 6 quick links, tailored to the current user's
 * active industry and its Bookings/Perm feature flags, for them to then
 * edit freely via My Quick Links. Only ever runs once — for a user with no
 * quick links of their own yet — so later edits are never overwritten.
 */
class GenerateDefaultQuickLinks
{
    use AsAction;

    /**
     * @return array<int, array{label: string, icon: string, url: string}>
     */
    public function handle(): array
    {
        return collect(QuickLinkCatalog::availableForCurrentUser())
            ->take(UserQuickLinkResource::MAX_QUICK_LINKS)
            ->values()
            ->all();
    }
}
