<?php

namespace App\Filament\Pages\Dashboards;

use App\Filament\Widgets\ComplianceGenericKpiOverview;
use App\Filament\Widgets\ComplianceItemVettingTable;
use Filament\Widgets\WidgetConfiguration;

/**
 * The generic-candidate equivalent of {@see ComplianceEducationDashboard} —
 * used for any industry other than Education/Healthcare when a
 * compliance-only user is active (see ComplianceDashboard::__construct()).
 * A generic candidate's requirements are configurable Compliance Items
 * rather than a fixed step count, so its buckets are ratio-based — see
 * {@see ComplianceItemVettingTable}.
 */
class ComplianceGenericDashboard implements DashboardInterface
{
    public function getWidgets(): array
    {
        return [
            ComplianceGenericKpiOverview::class,
            ...collect(ComplianceItemVettingTable::buckets())
                ->map(fn (array $bucket): WidgetConfiguration => new WidgetConfiguration(ComplianceItemVettingTable::class, [
                    'ratioFrom' => $bucket['from'],
                    'ratioTo' => $bucket['to'],
                    'bucketHeading' => $bucket['heading'],
                    'bucketColor' => $bucket['color'],
                ]))
                ->all(),
        ];
    }

    public function getTitle(): string
    {
        return 'Compliance';
    }

    /** @return int | array<string, ?int> */
    public function getColumns(): int|array
    {
        return 3;
    }
}
