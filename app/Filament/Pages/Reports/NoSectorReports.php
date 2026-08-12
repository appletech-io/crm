<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Widgets\NoIndustryWidget;

class NoSectorReports implements ReportsInterface
{
    public function getWidgets(): array
    {
        return [
            NoIndustryWidget::class,
        ];
    }

    public function getTitle(): string
    {
        return 'No sector set for user';
    }

    /** @return int | array<string, ?int> */
    public function getColumns(): int|array
    {
        return 2;
    }
}
