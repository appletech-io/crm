<?php

namespace App\Services\Candidates;

use App\Enums\CandidateAvailabilityStatus;
use App\Models\BookingDay;
use App\Models\Candidate;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CandidateMonthlyAvailability
{
    /**
     * Builds one row per day of the calendar month containing $monthStart. A
     * day the candidate already has an active booking on is forced to
     * "Booked" regardless of anything stored for that date — the
     * candidate/consultant can't override a real booking here.
     *
     * @return array<int, array{date: string, status: ?string, editable: bool}>
     */
    public static function forMonth(EducationCandidate|HealthcareCandidate|Candidate $candidate, Carbon $monthStart): array
    {
        $start = $monthStart->copy()->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $stored = $candidate->availabilities()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get()
            ->keyBy(fn ($availability): string => $availability->date->toDateString());

        $bookedDates = static::bookedDates($candidate, $start, $end);

        return collect(CarbonPeriod::create($start, $end))
            ->map(function (Carbon $date) use ($stored, $bookedDates): array {
                $dateString = $date->toDateString();
                $isBooked = $bookedDates->contains($dateString);

                return [
                    'date' => $dateString,
                    'status' => $isBooked
                        ? CandidateAvailabilityStatus::Booked->value
                        : $stored->get($dateString)?->status?->value,
                    'editable' => ! $isBooked,
                ];
            })
            ->values()
            ->all();
    }

    /** @return Collection<int, string> */
    protected static function bookedDates(EducationCandidate|HealthcareCandidate|Candidate $candidate, Carbon $start, Carbon $end): Collection
    {
        return BookingDay::query()
            ->whereHas('booking', fn ($query) => $query
                ->where('candidate_id', $candidate->id)
                ->where('candidate_type', $candidate::class))
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->whereNull('cancelled_at')
            ->get()
            ->map(fn (BookingDay $day): string => $day->date->toDateString())
            ->unique()
            ->values();
    }
}
