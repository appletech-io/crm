<?php

namespace App\Services\Reporting;

use App\Enums\VacancyEmploymentType;
use App\Models\VacancyPlacement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A "placement" here is one VacancyPlacement record — a single candidate
 * placed against a single vacancy position — bucketed by that placement's
 * own placed_at, and valued at its recorded actual_salary times the
 * vacancy's placement_fee_percentage. A multi-position vacancy filled one
 * candidate at a time therefore shows up incrementally as each candidate is
 * placed, not as one lump event once every position is filled.
 *
 * Only permanent vacancies count — temp roles are tracked through Booking
 * revenue instead (see BookingRevenuePeriodCalculator), so their placements
 * are excluded entirely here rather than reported at zero value, matching
 * this report's "Permanent Placements" framing.
 */
class PlacementPeriodCalculator
{
    /** @return Collection<int, array{weekStart: Carbon, count: int, value: float}> */
    public static function byWeek(Carbon $start, Carbon $end, ?int $consultantId = null, ?int $clientId = null): Collection
    {
        return self::placements($start, $end, $consultantId, $clientId)
            ->groupBy(fn (VacancyPlacement $placement): string => $placement->placed_at->copy()->startOfWeek(Carbon::MONDAY)->toDateString())
            ->map(fn (Collection $placements, string $weekStart): array => [
                'weekStart' => Carbon::parse($weekStart),
                'count' => $placements->count(),
                'value' => round($placements->sum(fn (VacancyPlacement $placement): float => self::value($placement)), 2),
            ])
            ->sortKeys()
            ->values();
    }

    /** @return Collection<int, array{clientId: int, count: int, value: float}> */
    public static function byClient(Carbon $start, Carbon $end, ?int $consultantId = null, ?int $clientId = null): Collection
    {
        return self::placements($start, $end, $consultantId, $clientId)
            ->groupBy(fn (VacancyPlacement $placement): int => $placement->vacancy->client_id)
            ->map(fn (Collection $placements, int $groupClientId): array => [
                'clientId' => $groupClientId,
                'count' => $placements->count(),
                'value' => round($placements->sum(fn (VacancyPlacement $placement): float => self::value($placement)), 2),
            ])
            ->values();
    }

    /** @return array{count: int, value: float, avgValue: float} */
    public static function totals(Carbon $start, Carbon $end, ?int $consultantId = null, ?int $clientId = null): array
    {
        $placements = self::placements($start, $end, $consultantId, $clientId);

        $value = round($placements->sum(fn (VacancyPlacement $placement): float => self::value($placement)), 2);
        $count = $placements->count();

        return [
            'count' => $count,
            'value' => $value,
            'avgValue' => $count > 0 ? round($value / $count, 2) : 0.0,
        ];
    }

    private static function value(VacancyPlacement $placement): float
    {
        if ($placement->actual_salary === null || $placement->vacancy->placement_fee_percentage === null) {
            return 0.0;
        }

        return $placement->actual_salary * ($placement->vacancy->placement_fee_percentage / 100);
    }

    /** @return Collection<int, VacancyPlacement> */
    private static function placements(Carbon $start, Carbon $end, ?int $consultantId, ?int $clientId): Collection
    {
        return VacancyPlacement::query()
            ->whereNotNull('placed_at')
            ->whereBetween('placed_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->whereHas('vacancy', function (Builder $query) use ($consultantId, $clientId): void {
                $query->forActiveIndustry()
                    ->where('employment_type', VacancyEmploymentType::Permanent->value)
                    ->when($consultantId, fn (Builder $q) => $q->where('consultant_id', $consultantId))
                    ->when($clientId, fn (Builder $q) => $q->where('client_id', $clientId));
            })
            ->with('vacancy')
            ->get();
    }
}
