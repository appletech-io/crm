<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ClientTypes\ClientTypeResource;
use App\Filament\Resources\JobTitles\JobTitleResource;
use App\Models\ClientType;
use App\Models\JobTitle;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class ClientSettingsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $jobTitlesCount = JobTitle::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->count();

        $clientTypesCount = ClientType::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->count();

        return [
            Stat::make('Job Titles', $jobTitlesCount)
                ->description('Job titles configured')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('primary')
                ->url(JobTitleResource::getUrl('index')),
            Stat::make('Client Types', $clientTypesCount)
                ->description('Client types configured')
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('primary')
                ->url(ClientTypeResource::getUrl('index')),
        ];
    }
}
