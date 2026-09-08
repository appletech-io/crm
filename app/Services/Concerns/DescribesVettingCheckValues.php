<?php

namespace App\Services\Concerns;

use App\Enums\ReferenceStatus;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use Carbon\CarbonInterface;

/**
 * The vetting answers that read the same way whichever sector a candidate
 * belongs to — shared by the per-sector BookingConfirmationChecks classes,
 * whose remaining rows differ (a TRN and safeguarding training for
 * education, professional registration for healthcare).
 *
 * Takes a union of the two candidate types rather than a plain Model,
 * because they share no parent beyond Eloquent's despite carrying the same
 * columns for these particular checks — the union is what lets static
 * analysis still see those columns.
 */
trait DescribesVettingCheckValues
{
    protected static function rightToWorkLabel(EducationCandidate|HealthcareCandidate $candidate): string
    {
        return match ($candidate->right_to_work_type) {
            'passport' => 'UK Passport',
            'visa' => 'Visa',
            'birth_certificate' => 'UK Birth Certificate',
            default => 'N/A',
        };
    }

    /**
     * "Yes" only when every reference on file is confirmed — a candidate
     * with one confirmed and one outstanding reference has not been
     * reference-checked.
     */
    protected static function referencesCheckedLabel(EducationCandidate|HealthcareCandidate $candidate): string
    {
        if (! $candidate->references()->exists()) {
            return 'N/A';
        }

        $allConfirmed = ! $candidate->references()->where('status', '!=', ReferenceStatus::Confirmed)->exists();

        return $allConfirmed ? 'Yes' : 'No';
    }

    protected static function medicalIssueLabel(EducationCandidate|HealthcareCandidate $candidate): string
    {
        if ($candidate->has_health_condition_or_disability !== 'yes') {
            return 'N/A';
        }

        return $candidate->health_condition_details ?: 'Yes';
    }

    protected static function overseasClearanceLabel(EducationCandidate|HealthcareCandidate $candidate): string
    {
        if ($candidate->lived_overseas_six_months !== 'yes') {
            return 'Not Required';
        }

        return $candidate->overseas_police_clearance_check === 'yes' ? 'Cleared' : 'Outstanding';
    }

    protected static function dateLabel(?CarbonInterface $date): string
    {
        return $date?->format('jS M Y') ?? 'N/A';
    }
}
