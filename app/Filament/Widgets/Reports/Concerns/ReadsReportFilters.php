<?php

namespace App\Filament\Widgets\Reports\Concerns;

use Illuminate\Support\Carbon;

/**
 * Every Reports widget reads the same page-level filters (set via
 * Reports::filtersForm()) through Filament's InteractsWithPageFilters trait
 * — this just gives them typed, defaulted accessors instead of each
 * repeating the same array lookups.
 */
trait ReadsReportFilters
{
    protected function periodStart(): Carbon
    {
        return Carbon::parse($this->pageFilters['start_date'] ?? now()->startOfMonth()->toDateString());
    }

    protected function periodEnd(): Carbon
    {
        return Carbon::parse($this->pageFilters['end_date'] ?? now()->toDateString());
    }

    protected function filterConsultantId(): ?int
    {
        $value = $this->pageFilters['consultant_id'] ?? null;

        return $value ? (int) $value : null;
    }

    protected function filterClientId(): ?int
    {
        $value = $this->pageFilters['client_id'] ?? null;

        return $value ? (int) $value : null;
    }
}
