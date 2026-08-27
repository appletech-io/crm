<?php

namespace App\Services\Booking;

use App\Enums\BookingDayPeriod;
use App\Enums\CandidateAvailabilityStatus;
use App\Models\CandidateAvailability;
use App\Models\JobTitle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BookingEligibility
{
    /**
     * Null if the candidate can work this job title — no candidate, no job
     * title, no qualification set, or the qualification has no configured
     * restrictions yet (an unconfigured allowed-list means unrestricted, not
     * blocked). Otherwise the reason to show in the form error.
     *
     * Duck-typed on Model rather than EducationCandidate|HealthcareCandidate
     * so this stays callable for any resolved candidate model — a candidate
     * model with no qualification_id column (e.g. the generic Candidate)
     * simply has no restriction to enforce.
     */
    public static function disallowedJobTitleReason(
        ?Model $candidate,
        ?int $jobTitleId,
    ): ?string {
        if (! $candidate || ! $jobTitleId || ! $candidate->qualification_id) {
            return null;
        }

        $qualification = $candidate->qualification;

        if (! $qualification) {
            return null;
        }

        $allowedJobTitleIds = $qualification->jobTitles()->pluck('job_titles.id');

        if ($allowedJobTitleIds->isEmpty() || $allowedJobTitleIds->contains($jobTitleId)) {
            return null;
        }

        $jobTitleName = JobTitle::find($jobTitleId)?->name ?? 'this job title';

        return "This candidate's qualification ({$qualification->name}) does not allow working as {$jobTitleName}.";
    }

    /**
     * A date with no CandidateAvailability record is treated as available —
     * this only flags dates the candidate has actively marked as
     * unavailable (or restricted to the other half of the day).
     *
     * @param  class-string  $candidateType
     * @param  array<int, array<string, mixed>>  $dayPeriods  Same shape as BookingOverlap::conflictingDates().
     * @return Collection<int, string>
     */
    public static function unavailableDates(string $candidateType, mixed $candidateId, array $dayPeriods): Collection
    {
        $incoming = collect($dayPeriods)
            ->filter(fn (array $entry): bool => filled($entry['date'] ?? null))
            ->keyBy('date');

        if (blank($candidateId) || $incoming->isEmpty()) {
            return collect();
        }

        return CandidateAvailability::query()
            ->where('candidate_id', $candidateId)
            ->where('candidate_type', $candidateType)
            ->whereIn('status', [
                CandidateAvailabilityStatus::NotAvailable,
                CandidateAvailabilityStatus::AvailableAm,
                CandidateAvailabilityStatus::AvailablePm,
            ])
            ->get()
            ->filter(function (CandidateAvailability $availability) use ($incoming): bool {
                $entry = $incoming->get($availability->date->toDateString());

                if (! $entry) {
                    return false;
                }

                $requested = BookingDayPeriod::from($entry['period'] ?? BookingDayPeriod::FullDay->value);

                return match ($availability->status) {
                    CandidateAvailabilityStatus::NotAvailable => true,
                    CandidateAvailabilityStatus::AvailableAm => in_array($requested, [BookingDayPeriod::FullDay, BookingDayPeriod::Pm], true),
                    CandidateAvailabilityStatus::AvailablePm => in_array($requested, [BookingDayPeriod::FullDay, BookingDayPeriod::Am], true),
                    default => false,
                };
            })
            ->map(fn (CandidateAvailability $availability): string => $availability->date->toDateString())
            ->unique()
            ->sort()
            ->values();
    }
}
