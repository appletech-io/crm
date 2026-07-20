<?php

namespace App\Services\Booking;

use App\Enums\TimesheetFrequency;
use App\Models\Company;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class TimesheetPeriod
{
    /** A known Friday used purely to keep bi-weekly blocks aligned to a stable 14-day grid. */
    private const BIWEEKLY_ANCHOR = '2024-01-05';

    /** @return array{start: Carbon, end: Carbon} */
    public static function containing(Company $company, CarbonInterface $date): array
    {
        return match ($company->timesheet_frequency) {
            TimesheetFrequency::Weekly => self::weeklyPeriod($date),
            TimesheetFrequency::Biweekly => self::biweeklyPeriod($date),
            TimesheetFrequency::Monthly => self::monthlyPeriod($company, $date),
        };
    }

    /** @return array{start: Carbon, end: Carbon} */
    public static function current(Company $company): array
    {
        return self::containing($company, Carbon::now());
    }

    /** @return array{start: Carbon, end: Carbon} */
    public static function next(Company $company, CarbonInterface $periodStart): array
    {
        $currentPeriod = self::containing($company, $periodStart);

        return self::containing($company, $currentPeriod['end']->copy()->addDay());
    }

    /** @return array{start: Carbon, end: Carbon} */
    public static function previous(Company $company, CarbonInterface $periodStart): array
    {
        $currentPeriod = self::containing($company, $periodStart);

        return self::containing($company, $currentPeriod['start']->copy()->subDay());
    }

    /**
     * The cutoff/end date of every period whose cutoff itself falls within the given range,
     * for use as the selectable dates in a calendar restricted to valid period boundaries.
     *
     * @return array<int, string>
     */
    public static function selectableDatesBetween(Company $company, CarbonInterface $rangeStart, CarbonInterface $rangeEnd): array
    {
        $rangeStart = $rangeStart->copy()->startOfDay();
        $rangeEnd = $rangeEnd->copy()->endOfDay();

        $dates = [];
        $period = self::containing($company, $rangeStart);

        while ($period['end']->lte($rangeEnd)) {
            if ($period['end']->gte($rangeStart)) {
                $dates[] = $period['end']->toDateString();
            }

            $period = self::next($company, $period['start']);
        }

        return $dates;
    }

    /** @return array{start: Carbon, end: Carbon} */
    private static function weeklyPeriod(CarbonInterface $date): array
    {
        return [
            'start' => $date->copy()->startOfWeek(Carbon::SATURDAY),
            'end' => $date->copy()->endOfWeek(Carbon::FRIDAY),
        ];
    }

    /** @return array{start: Carbon, end: Carbon} */
    private static function biweeklyPeriod(CarbonInterface $date): array
    {
        $weekEnd = $date->copy()->endOfWeek(Carbon::FRIDAY);

        $anchor = Carbon::parse(self::BIWEEKLY_ANCHOR)->endOfDay();
        $weeksBetween = (int) $anchor->diffInWeeks($weekEnd, false);
        $remainder = (($weeksBetween % 2) + 2) % 2;

        if ($remainder !== 0) {
            $weekEnd = $weekEnd->copy()->addWeek();
        }

        return [
            'start' => $weekEnd->copy()->subDays(13)->startOfDay(),
            'end' => $weekEnd->copy()->endOfDay(),
        ];
    }

    /** @return array{start: Carbon, end: Carbon} */
    private static function monthlyPeriod(Company $company, CarbonInterface $date): array
    {
        $anchorDay = $company->timesheet_day_of_month ?? 1;

        $start = self::monthlyAnchorDate($date, $anchorDay);

        if ($date->lt($start)) {
            $start = self::monthlyAnchorDate($start->copy()->subMonthNoOverflow(), $anchorDay);
        }

        $nextStart = self::monthlyAnchorDate($start->copy()->addMonthNoOverflow(), $anchorDay);

        return [
            'start' => $start,
            'end' => $nextStart->copy()->subDay()->endOfDay(),
        ];
    }

    /** Resolve the anchor day within the month of the given reference date, clamped to that month's length. */
    private static function monthlyAnchorDate(CarbonInterface $monthReference, int $anchorDay): CarbonInterface
    {
        return $monthReference->copy()->startOfDay()->day(min($anchorDay, $monthReference->daysInMonth));
    }
}
