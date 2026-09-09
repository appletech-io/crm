<?php

namespace App\Services\Payroll;

use App\Enums\BookingDayPeriod;
use App\Models\BookingDay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Shapes a client's booking days for a payroll period into the per-
 * contractor rows and approval state a client timesheet PDF renders —
 * kept apart from the PDF/ZIP mechanics in ExportPayrollTimesheetsZipAction
 * so this grouping/approval logic is directly testable without needing to
 * parse rendered PDF output.
 */
class ClientTimesheetData
{
    /**
     * One entry per candidate booked at this client during the period, each
     * with every one of their (already non-cancelled) days across however
     * many bookings they had here — a candidate booked twice at the same
     * client in the same week reads as one contractor section, not two.
     *
     * @param  Collection<int, BookingDay>  $days  every non-cancelled day for one client in the period
     * @return array<int, array{
     *     name: string,
     *     rows: array<int, array{date: Carbon, period: BookingDayPeriod, job_title: ?string, rate: ?float}>,
     *     approval: ?array{name: string, date: Carbon},
     * }>
     */
    public static function contractorsFor(Collection $days): array
    {
        return $days
            ->groupBy(fn (BookingDay $day) => "{$day->booking->candidate_type}|{$day->booking->candidate_id}")
            ->map(function (Collection $candidateDays): array {
                $sorted = $candidateDays->sortBy('date')->values();

                return [
                    'name' => static::candidateName($sorted->first()->booking->candidate),
                    'rows' => $sorted->map(fn (BookingDay $day): array => [
                        'date' => $day->date,
                        'period' => $day->period,
                        'job_title' => $day->booking->jobTitle?->name,
                        'rate' => $day->isCancelled() ? null : $day->chargeRate(),
                    ])->all(),
                    'approval' => static::approvalFor($sorted),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    private static function candidateName(?Model $candidate): string
    {
        if (! $candidate) {
            return 'Unknown candidate';
        }

        $name = trim("{$candidate->first_name} {$candidate->last_name}");

        return $candidate->trashed() ? "{$name} (deleted)" : $name;
    }

    /**
     * Only a contractor whose every displayed day has actually been
     * approved gets a signature — a section with any day still pending
     * shows that instead, rather than fabricating an approval that hasn't
     * happened yet. The latest approval among their days stands in for the
     * whole contractor, since in practice a week is normally approved
     * together in one action.
     *
     * @param  Collection<int, BookingDay>  $days
     * @return ?array{name: string, date: Carbon}
     */
    private static function approvalFor(Collection $days): ?array
    {
        if ($days->contains(fn (BookingDay $day): bool => $day->approved_at === null)) {
            return null;
        }

        /** @var BookingDay $latest */
        $latest = $days->sortByDesc('approved_at')->first();

        return [
            'name' => $latest->approvedBy?->name ?? 'Unknown',
            'date' => $latest->approved_at,
        ];
    }
}
