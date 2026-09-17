<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ClientContactJobTitles\ClientContactJobTitleResource;
use App\Filament\Resources\ClientPools\ClientPoolResource;
use App\Filament\Resources\ClientTypes\ClientTypeResource;
use App\Models\ClientContactJobTitle;
use App\Models\ClientPool;
use App\Models\ClientType;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class ClientSettingsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    /**
     * Contact Job Titles and Client Types are company-wide config resources
     * (admin/site_admin only — see their own canViewAny()); Client Pools is
     * always a consultant's own data, and ClientPoolResource itself has no
     * role restriction. A consultant reaching this page (see
     * ClientSettings::canAccess()) only sees the stat they can actually
     * open, rather than a card that 403s when clicked.
     */
    protected function getStats(): array
    {
        $poolsCount = ClientPool::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->where('user_id', Auth::id())
            ->where('is_primary', false)
            ->count();

        $poolStat = Stat::make('Client Pools', $poolsCount)
            ->description('Your extra pools for organising clients')
            ->descriptionIcon('heroicon-m-user-group')
            ->color('primary')
            ->url(ClientPoolResource::getUrl('index'));

        if (! (Auth::user()?->hasAnyRole(['admin', 'site_admin']) ?? false)) {
            return [$poolStat];
        }

        $contactJobTitlesCount = ClientContactJobTitle::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->count();

        $clientTypesCount = ClientType::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->count();

        return [
            Stat::make('Contact Job Titles', $contactJobTitlesCount)
                ->description('Client contact job titles configured')
                ->descriptionIcon('heroicon-m-identification')
                ->color('primary')
                ->url(ClientContactJobTitleResource::getUrl('index')),
            Stat::make('Client Types', $clientTypesCount)
                ->description('Client types configured')
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('primary')
                ->url(ClientTypeResource::getUrl('index')),
            $poolStat,
        ];
    }
}
