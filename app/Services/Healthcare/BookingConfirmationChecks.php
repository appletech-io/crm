<?php

namespace App\Services\Healthcare;

use App\Models\HealthcareCandidate;
use App\Services\Concerns\DescribesVettingCheckValues;

/**
 * The vetting summary table printed on a healthcare booking's confirmation
 * PDF. Mirrors {@see \App\Services\Education\BookingConfirmationChecks},
 * minus the rows that only exist on an education candidate (TRN, TRA/NCTL
 * sanctions, safeguarding and Benedict's Law training) and plus the ones
 * the healthcare vetting wizard collects instead: the candidate's
 * professional registration and, where their qualification was earned
 * abroad, their NARIC statement.
 */
class BookingConfirmationChecks
{
    use DescribesVettingCheckValues;

    /** @return array<int, array{label: string, value: string}> */
    public static function for(HealthcareCandidate $candidate): array
    {
        return [
            ['label' => 'Date of Birth', 'value' => self::dateLabel($candidate->date_of_birth)],
            ['label' => 'NI Number', 'value' => $candidate->ni_number ?? 'N/A'],
            ['label' => 'Address Checked', 'value' => self::dateLabel($candidate->proof_of_address_checked_at)],
            ['label' => 'Right to Work Type', 'value' => self::rightToWorkLabel($candidate)],
            ['label' => 'Reference(s) Checked', 'value' => self::referencesCheckedLabel($candidate)],
            ['label' => 'Qualification', 'value' => $candidate->qualification->name ?? 'N/A'],
            ['label' => 'Registration Body', 'value' => $candidate->professional_registration_body ?? 'N/A'],
            ['label' => 'Registration No', 'value' => $candidate->professional_registration_number ?? 'N/A'],
            ['label' => 'Registration Checked', 'value' => self::dateLabel($candidate->professional_registration_checked_at)],
            ['label' => 'Has NARIC', 'value' => $candidate->has_naric === 'yes' ? 'Yes' : 'N/A'],
            ['label' => 'DBS No', 'value' => $candidate->dbs_certificate_number ?? 'N/A'],
            ['label' => 'DBS Checked Date', 'value' => self::dateLabel($candidate->dbs_checked_date)],
            ['label' => 'DBS Update Service', 'value' => self::dateLabel($candidate->update_service_checked_at)],
            ['label' => 'Any Medical Issue', 'value' => self::medicalIssueLabel($candidate)],
            ['label' => 'Overseas Police Clearance', 'value' => self::overseasClearanceLabel($candidate)],
        ];
    }
}
