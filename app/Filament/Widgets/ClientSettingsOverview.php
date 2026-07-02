<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\JobTitles\JobTitleResource;
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

        return [
            Stat::make('Job Titles', $jobTitlesCount)
                ->description('Job titles configured')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('primary')
                ->url(JobTitleResource::getUrl('index')),
        ];
    }
}
