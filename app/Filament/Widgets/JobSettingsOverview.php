<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\JobStatuses\JobStatusResource;
use App\Models\JobStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class JobSettingsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $jobStatusesCount = JobStatus::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->count();

        return [
            Stat::make('Job Statuses', $jobStatusesCount)
                ->description('Statuses and automations configured')
                ->descriptionIcon('heroicon-m-tag')
                ->color('primary')
                ->url(JobStatusResource::getUrl('index')),
        ];
    }
}
