<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Widgets\Reports\Concerns\ReadsReportFilters;
use App\Services\Reporting\BookingRevenuePeriodCalculator;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class TopClientsTable extends Widget
{
    use InteractsWithPageFilters;
    use ReadsReportFilters;

    protected string $view = 'filament.widgets.reports.top-clients-table';

    protected int|string|array $columnSpan = 'full';

    /** @return Collection<int, array{clientId: int, clientName: string, revenue: float, cost: float, margin: float, bookings: int}> */
    public function rows(): Collection
    {
        return BookingRevenuePeriodCalculator::byClient(
            $this->periodStart(),
            $this->periodEnd(),
            $this->filterConsultantId(),
            $this->filterClientId(),
        )->take(10);
    }
}
