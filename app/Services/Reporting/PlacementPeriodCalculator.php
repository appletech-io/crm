<?php

namespace App\Services\Reporting;

use App\Models\Vacancy;
use App\Observers\VacancyObserver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A "placement" is a vacancy that reached a job status flagged
 * is_filled_status, bucketed by when that happened
 * ({@see VacancyObserver::syncFilledAt()} keeps filled_at
 * current). estimatedPlacementValue() (already on the model) supplies the
 * fee estimate — not recomputed here.
 */
class PlacementPeriodCalculator
{
    /** @return Collection<int, array{weekStart: Carbon, count: int, value: float}> */
    public static function byWeek(Carbon $start, Carbon $end, ?int $consultantId = null, ?int $clientId = null): Collection
    {
        return self::filledVacancies($start, $end, $consultantId, $clientId)
            ->groupBy(fn (Vacancy $vacancy): string => $vacancy->filled_at->copy()->startOfWeek(Carbon::MONDAY)->toDateString())
            ->map(fn (Collection $vacancies, string $weekStart): array => [
                'weekStart' => Carbon::parse($weekStart),
                'count' => $vacancies->count(),
                'value' => round($vacancies->sum(fn (Vacancy $vacancy): float => $vacancy->estimatedPlacementValue() ?? 0), 2),
            ])
            ->sortKeys()
            ->values();
    }

    /** @return array{count: int, value: float, avgValue: float} */
    public static function totals(Carbon $start, Carbon $end, ?int $consultantId = null, ?int $clientId = null): array
    {
        $vacancies = self::filledVacancies($start, $end, $consultantId, $clientId);

        $value = round($vacancies->sum(fn (Vacancy $vacancy): float => $vacancy->estimatedPlacementValue() ?? 0), 2);
        $count = $vacancies->count();

        return [
            'count' => $count,
            'value' => $value,
            'avgValue' => $count > 0 ? round($value / $count, 2) : 0.0,
        ];
    }

    /** @return Collection<int, Vacancy> */
    private static function filledVacancies(Carbon $start, Carbon $end, ?int $consultantId, ?int $clientId): Collection
    {
        return Vacancy::query()
            ->forActiveIndustry()
            ->whereHas('jobStatus', fn (Builder $query) => $query->where('is_filled_status', true))
            ->whereBetween('filled_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->when($consultantId, fn (Builder $query) => $query->where('consultant_id', $consultantId))
            ->when($clientId, fn (Builder $query) => $query->where('client_id', $clientId))
            ->get();
    }
}
