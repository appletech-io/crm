<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\EducationCandidate;

class CandidateVettingRequirements
{
    /** @return array<string, array{label: string, description: string, complete: bool}> */
    public static function for(EducationCandidate $candidate): array
    {
        return [
            'dbs' => [
                'label' => 'DBS',
                'description' => 'Candidate has a DBS on file with a certificate number.',
                'complete' => $candidate->has_dbs === 'yes' && filled($candidate->dbs_certificate_number),
            ],
            'cv' => [
                'label' => 'CV',
                'description' => 'CV document has been uploaded.',
                'complete' => $candidate->documents()->where('document_type', DocumentType::Cv)->exists(),
            ],
            'headshot_photo' => [
                'label' => 'Headshot Photo',
                'description' => 'Headshot photo has been uploaded.',
                'complete' => $candidate->documents()->where('document_type', DocumentType::Photo)->exists(),
            ],
            'skills' => [
                'label' => 'Skills',
                'description' => 'At least one skill has been recorded for the candidate.',
                'complete' => $candidate->skills()->exists(),
            ],
            'not_barred' => [
                'label' => 'Not Barred',
                'description' => 'Candidate has been checked against the barred list and cleared.',
                'complete' => $candidate->barred_list_check === 'yes',
            ],
            'overseas_clearance' => [
                'label' => 'Overseas Police Clearance',
                'description' => 'Candidate has been cleared for overseas police checks, if applicable.',
                'complete' => $candidate->lived_overseas_six_months !== 'yes' || $candidate->overseas_police_clearance_check === 'yes',
            ],
            'proof_of_address' => [
                'label' => 'Proof of Address',
                'description' => 'Uploaded proof of address matches the candidate\'s stored address.',
                'complete' => $candidate->proof_of_address_match === 'yes',
            ],
            'proof_of_ni' => [
                'label' => 'Proof of NI',
                'description' => 'Uploaded proof of NI matches the candidate\'s stored NI number.',
                'complete' => $candidate->ni_number_match === 'yes',
            ],
            'trn' => [
                'label' => 'TRN',
                'description' => 'Teacher Reference Number is set and its issue date has been recorded.',
                'complete' => blank($candidate->trn_number) || filled($candidate->trn_issue_date),
            ],
            'safeguarding' => [
                'label' => 'Safeguarding Training',
                'description' => 'Safeguarding training has been checked and certified.',
                'complete' => filled($candidate->safeguarding_certified_date),
            ],
            'prevent_training' => [
                'label' => 'Prevent Training',
                'description' => 'Prevent training has been checked and completed.',
                'complete' => $candidate->prevent_training_completed === 'yes',
            ],
            'right_to_work' => [
                'label' => static::rightToWorkLabel($candidate),
                'description' => 'Right to work has been established: passport with document uploaded, birth certificate with document uploaded and NI number set, or visa set and confirmed.',
                'complete' => static::rightToWorkComplete($candidate),
            ],
        ];
    }

    protected static function rightToWorkLabel(EducationCandidate $candidate): string
    {
        $mode = match ($candidate->right_to_work_type) {
            'passport' => 'UK Passport',
            'visa' => 'Visa',
            'birth_certificate' => 'UK Birth Certificate',
            default => null,
        };

        return $mode ? "Right to Work ({$mode})" : 'Right to Work';
    }

    protected static function rightToWorkComplete(EducationCandidate $candidate): bool
    {
        return match ($candidate->right_to_work_type) {
            'passport' => $candidate->documents()->where('document_type', DocumentType::Passport)->exists(),
            'birth_certificate' => filled($candidate->ni_number)
                && $candidate->documents()->where('document_type', DocumentType::BirthCertificate)->exists(),
            'visa' => filled($candidate->visa_share_code)
                && filled($candidate->visa_issue_date)
                && filled($candidate->visa_expiry_date),
            default => false,
        };
    }

    public static function isComplete(EducationCandidate $candidate): bool
    {
        return collect(self::for($candidate))->every(fn (array $check): bool => $check['complete']);
    }
}
