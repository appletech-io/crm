<?php

namespace App\Filament\Pages\Dashboards;

use App\Filament\Resources\EducationVetting\VettingResource;
use App\Filament\Widgets\ComplianceEducationKpiOverview;
use App\Filament\Widgets\ComplianceVettingTable;
use App\Models\EducationCandidate;
use Filament\Widgets\WidgetConfiguration;

class ComplianceEducationDashboard implements DashboardInterface
{
    /** @var array<int, string> */
    private const STEP_LABELS = [
        'Personal Details',
        'Pay Rates',
        'Skills',
        'Documents',
        'Security Checks',
        'TRA Checks',
        'DBS',
        'References',
        'Confirm',
    ];

    public function getWidgets(): array
    {
        return [
            ComplianceEducationKpiOverview::class,
            ...collect(ComplianceVettingTable::buckets(count(self::STEP_LABELS)))
                ->map(fn (array $bucket): WidgetConfiguration => new WidgetConfiguration(ComplianceVettingTable::class, [
                    'candidateModelClass' => EducationCandidate::class,
                    'vettingResourceClass' => VettingResource::class,
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
