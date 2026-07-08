<?php

namespace App\Exceptions\Dbs;

use App\Models\EducationCandidate;

class InvalidUpdateServiceResponseException extends DbsUpdateServiceException
{
    public function __construct(EducationCandidate $candidate, public readonly string $responseBody)
    {
        parent::__construct('Unable to parse the DBS Update Service response.', $candidate);
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return array_merge(parent::context(), [
            'dbs_certificate_number' => $this->candidate?->dbs_certificate_number,
            'response_body' => $this->responseBody,
        ]);
    }
}
