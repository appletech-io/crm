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

    /**
     * A non-admin only ever sees their own figures here — the Consultant
     * filter itself is hidden from them (see Reports::filtersForm()), so
     * this ignores whatever's in page filter state and forces their own id
     * instead of trusting a value they can't actually control.
     */
    protected function filterConsultantId(): ?int
    {
        $user = auth()->user();

        if ($user && ! $user->isAdmin()) {
            return $user->id;
        }

        $value = $this->pageFilters['consultant_id'] ?? null;

        return $value ? (int) $value : null;
    }

    protected function filterClientId(): ?int
    {
        $value = $this->pageFilters['client_id'] ?? null;

        return $value ? (int) $value : null;
    }
}
