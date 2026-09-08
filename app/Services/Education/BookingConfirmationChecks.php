<?php

namespace App\Services\Education;

use App\Models\EducationCandidate;
use App\Services\Concerns\DescribesVettingCheckValues;

/**
 * The vetting summary table printed on an education booking's confirmation
 * PDF — the checks a school needs to see before a candidate walks in.
 * {@see \App\Services\Healthcare\BookingConfirmationChecks} is the
 * healthcare equivalent, paired the same way the sectors' existing
 * CandidateVettingRequirements and CandidateSearchService classes are.
 */
class BookingConfirmationChecks
{
    use DescribesVettingCheckValues;

    /** @return array<int, array{label: string, value: string}> */
    public static function for(EducationCandidate $candidate): array
    {
        return [
            ['label' => 'Date of Birth', 'value' => self::dateLabel($candidate->date_of_birth)],
            ['label' => 'NI Number', 'value' => $candidate->ni_number ?? 'N/A'],
            ['label' => 'Address Checked', 'value' => self::dateLabel($candidate->proof_of_address_checked_at)],
            ['label' => 'Right to Work Type', 'value' => self::rightToWorkLabel($candidate)],
            ['label' => 'Reference(s) Checked', 'value' => self::referencesCheckedLabel($candidate)],
            ['label' => 'Qualification', 'value' => $candidate->qualification->name ?? 'N/A'],
            ['label' => 'Safeguarding Training', 'value' => self::dateLabel($candidate->safeguarding_certified_date)],
            ['label' => 'Benedict\'s Law Training', 'value' => self::dateLabel($candidate->benedicts_law_issue_date)],
            ['label' => 'TRN', 'value' => $candidate->trn_number ?? 'N/A'],
            ['label' => 'TRA/NCTL Sanctions', 'value' => $candidate->sanctions === 'yes' ? 'Sanctions' : 'No Sanctions'],
            ['label' => 'DBS No', 'value' => $candidate->dbs_certificate_number ?? 'N/A'],
            ['label' => 'DBS Checked Date', 'value' => self::dateLabel($candidate->dbs_checked_date)],
            ['label' => 'DBS Update Service', 'value' => self::dateLabel($candidate->update_service_checked_at)],
            ['label' => 'Any Medical Issue', 'value' => self::medicalIssueLabel($candidate)],
            ['label' => 'Overseas Police Clearance', 'value' => self::overseasClearanceLabel($candidate)],
        ];
    }
}
