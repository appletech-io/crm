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

    protected function getStats(): array
    {
        $contactJobTitlesCount = ClientContactJobTitle::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->count();

        $clientTypesCount = ClientType::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->count();

        $poolsCount = ClientPool::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->where(fn ($q) => $q
                ->where('user_id', Auth::id())
                ->orWhere(fn ($q) => $q->where('company_pool', true)->whereNull('user_id'))
            )
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
            Stat::make('Client Pools', $poolsCount)
                ->description('Your pools and company pools')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary')
                ->url(ClientPoolResource::getUrl('index')),
        ];
    }
}
