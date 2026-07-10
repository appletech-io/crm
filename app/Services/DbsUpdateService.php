<?php

namespace App\Services;

use App\Exceptions\Dbs\InvalidUpdateServiceResponseException;
use App\Exceptions\Dbs\MissingCandidateDetailsException;
use App\Exceptions\Dbs\MissingCertificateNumberException;
use App\Exceptions\Dbs\MissingCompanyLegalNameException;
use App\Exceptions\Dbs\UpdateServiceCheckRejectedException;
use App\Models\EducationCandidate;
use Illuminate\Support\Facades\Http;

class DbsUpdateService
{
    private const string ENDPOINT = 'https://secure.crbonline.gov.uk/crsc/api/status';

    /**
     * Perform a DBS Update Service status check for the given candidate and
     * store the resulting status on the candidate record.
     */
    public function check(EducationCandidate $candidate): string
    {
        if (! $candidate->dbs_certificate_number) {
            throw new MissingCertificateNumberException($candidate);
        }

        if (! $candidate->date_of_birth || ! $candidate->first_name || ! $candidate->last_name) {
            throw new MissingCandidateDetailsException($candidate);
        }

        if (! $candidate->company?->legal_name) {
            throw new MissingCompanyLegalNameException($candidate);
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
            throw new InvalidUpdateServiceResponseException($candidate, $response->body());
        }

        $resultType = (string) ($result->statusCheckResultType ?? '');

        if ($resultType !== 'SUCCESS') {
            throw new UpdateServiceCheckRejectedException($candidate, $resultType);
        }

        $status = (string) ($result->status ?? '');

        $candidate->update(['update_service_response' => $status]);

        return $status;
    }
}
