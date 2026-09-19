<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\CareLogsOverview;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * A quick link on the Healthcare dashboard to the staff Care Logs overview.
 * Only ever rendered when active_industry_uses_care_logging() is on, since
 * HealthcareDashboard::getWidgets() only includes this class in that case —
 * not registered on any other industry's dashboard at all.
 */
class CareLogsQuickLink extends StatsOverviewWidget
{
    /** @return array<Stat> */
    protected function getStats(): array
    {
        $count = CareLogsOverview::outstandingCount();

        return [
            Stat::make('Outstanding Care Logs', (string) $count)
                ->description($count > 0 ? 'Shifts still needing a candidate log' : 'All shifts logged')
                ->descriptionIcon('heroicon-o-clipboard-document-list')
                ->color($count > 0 ? 'danger' : 'success')
                ->url(CareLogsOverview::getUrl()),
        ];
    }

    /** @return int | array<string, ?int> | null */
    protected function getColumns(): int|array|null
    {
        return 1;
    }
}
