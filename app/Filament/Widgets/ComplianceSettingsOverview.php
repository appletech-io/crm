<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ComplianceItemJobTitles\ComplianceItemJobTitleResource;
use App\Filament\Resources\ComplianceItems\ComplianceItemResource;
use App\Models\ComplianceItem;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class ComplianceSettingsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $complianceItemsCount = ComplianceItem::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->count();

        return [
            Stat::make('Compliance Items', $complianceItemsCount)
                ->description('Compliance items configured')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('primary')
                ->url(ComplianceItemResource::getUrl('index')),

            Stat::make('Required Job Titles', $complianceItemsCount)
                ->description('Manage which job titles require which items')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('primary')
                ->url(ComplianceItemJobTitleResource::getUrl('index')),
        ];
    }
}
