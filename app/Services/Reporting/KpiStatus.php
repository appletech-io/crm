<?php

namespace App\Services\Reporting;

/**
 * Red/amber/green scoring for a single KPI — every KPI on the consultant
 * performance dashboard (GP, candidate days, working candidates, clients
 * booked, rebook rate) is "higher is better", so one function covers all of
 * them rather than each needing its own comparison.
 */
class KpiStatus
{
    private const AMBER_THRESHOLD = 0.8;

    /**
     * Null (no color) when there's nothing to judge — no target has been
     * set yet, or there's no actual figure to compare (e.g. a rebook rate
     * that's null because nothing was booked this week at all).
     */
    public static function for(?float $actual, ?float $target): ?string
    {
        if ($target === null || $target <= 0 || $actual === null) {
            return null;
        }

        return match (true) {
            $actual >= $target => 'success',
            $actual >= $target * self::AMBER_THRESHOLD => 'warning',
            default => 'danger',
        };
    }
}
