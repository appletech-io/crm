<?php

namespace App\Services\Healthcare;

use App\Models\HealthcareCandidate;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DbsUpdateService
{
    /**
     * The only status returned by the Update Service that confirms the
     * certificate is unchanged since issue.
     */
    public const string VALID_STATUS = 'BLANK_NO_NEW_INFO';

    private const string ENDPOINT = 'https://secure.crbonline.gov.uk/crsc/api/status';

    /**
     * Perform a DBS Update Service status check for the given candidate and
     * store the resulting status on the candidate record.
     */
    public function check(HealthcareCandidate $candidate): string
    {
        if (! $candidate->dbs_certificate_number) {
            throw new RuntimeException('Candidate does not have a DBS certificate number to check.');
        }

        if (! $candidate->date_of_birth || ! $candidate->first_name || ! $candidate->last_name) {
            throw new RuntimeException('Candidate is missing details required for a DBS Update Service check.');
        }

        if (! $candidate->company?->legal_name) {
            throw new RuntimeException("Candidate's company has no legal name set for a DBS Update Service check.");
        }

        $response = Http::get(self::ENDPOINT.'/'.$candidate->dbs_certificate_number, [
            'dateOfBirth' => $candidate->date_of_birth->format('d/m/Y'),
            'surname' => $candidate->last_name,
            'hasAgreedTermsAndConditions' => 'true',
            'organisationName' => $candidate->company->legal_name,
            'employeeForename' => $candidate->first_name,
            'employeeSurname' => $candidate->last_name,
        ])->throw();

        $result = simplexml_load_string($response->body());

        if ($result === false) {
            throw new RuntimeException('Unable to parse the DBS Update Service response.');
        }

        $resultType = (string) ($result->statusCheckResultType ?? '');

        if ($resultType !== 'SUCCESS') {
            throw new RuntimeException("DBS Update Service check was not successful: {$resultType}");
        }

        $status = (string) ($result->status ?? '');

        $candidate->update([
            'update_service_response' => $status,
            'update_service_checked_at' => now(),
        ]);

        return $status;
    }
}
