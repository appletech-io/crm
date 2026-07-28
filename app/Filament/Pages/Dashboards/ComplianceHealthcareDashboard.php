<?php

namespace App\Filament\Pages\Dashboards;

use App\Filament\Resources\HealthcareVetting\HealthcareVettingResource;
use App\Filament\Widgets\ComplianceHealthcareKpiOverview;
use App\Filament\Widgets\ComplianceVettingTable;
use App\Models\HealthcareCandidate;
use Filament\Widgets\WidgetConfiguration;

class ComplianceHealthcareDashboard implements DashboardInterface
{
    /** @var array<int, string> */
    private const STEP_LABELS = [
        'Personal Details',
        'Pay Rates',
        'Skills',
        'Documents',
        'Security Checks',
        'Professional Registration',
        'DBS',
        'References',
        'Confirm',
    ];

    public function getWidgets(): array
    {
        return [
            ComplianceHealthcareKpiOverview::class,
            ...collect(ComplianceVettingTable::buckets(count(self::STEP_LABELS)))
                ->map(fn (array $bucket): WidgetConfiguration => new WidgetConfiguration(ComplianceVettingTable::class, [
                    'candidateModelClass' => HealthcareCandidate::class,
                    'vettingResourceClass' => HealthcareVettingResource::class,
                    'stepLabelsList' => self::STEP_LABELS,
                    'stepFrom' => $bucket['from'],
                    'stepTo' => $bucket['to'],
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
