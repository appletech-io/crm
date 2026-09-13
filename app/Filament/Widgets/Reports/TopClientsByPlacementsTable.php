<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Widgets\Reports\Concerns\ReadsReportFilters;
use App\Models\Client;
use App\Services\Reporting\PlacementPeriodCalculator;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * The IT/no-bookings equivalent of {@see TopClientsTable} — permanent
 * placement fee value per client instead of temp booking revenue, since an
 * industry with no Bookings has no revenue/margin figures to rank clients
 * by.
 */
class TopClientsByPlacementsTable extends Widget
{
    use InteractsWithPageFilters;
    use ReadsReportFilters;

    protected string $view = 'filament.widgets.reports.top-clients-by-placements-table';

    protected int|string|array $columnSpan = 'full';

    /** @return Collection<int, array{clientId: int, clientName: string, count: int, value: float}> */
    public function rows(): Collection
    {
        $byClient = PlacementPeriodCalculator::byClient(
            $this->periodStart(),
            $this->periodEnd(),
            $this->filterConsultantId(),
            $this->filterClientId(),
        );

        $clientNames = Client::query()
            ->whereIn('id', $byClient->pluck('clientId'))
            ->pluck('name', 'id');

        return $byClient
            ->map(fn (array $row): array => [...$row, 'clientName' => $clientNames->get($row['clientId'], 'Unknown client')])
            ->sortByDesc('value')
            ->take(10)
            ->values();
    }
}
