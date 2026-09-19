<?php

namespace App\Filament\Pages\Dashboards;

use App\Filament\Widgets\CareLogsQuickLink;
use App\Filament\Widgets\ConsultantPerformanceSummary;
use App\Filament\Widgets\EducationConsultantLeaderboard;
use App\Filament\Widgets\HealthcareConsultantKpiOverview;

class HealthcareDashboard implements DashboardInterface
{
    public function getWidgets(): array
    {
        $widgets = [
            ConsultantPerformanceSummary::class,
            HealthcareConsultantKpiOverview::class,
            EducationConsultantLeaderboard::class,
        ];

        // Only ever shown once a site admin has switched Care Logging on
        // for this company+industry (see CompanyFeaturesForm) — off by
        // default, same as every other opt-in feature flag in this app.
        if (active_industry_uses_care_logging()) {
            $widgets[] = CareLogsQuickLink::class;
        }

        return $widgets;
    }

    public function getTitle(): string
    {
        return 'Home';
    }

    /** @return int | array<string, ?int> */
    public function getColumns(): int|array
    {
        return 2;
    }
}
