<?php

namespace App\Exceptions\Dbs;

use App\Models\EducationCandidate;

class UpdateServiceCheckRejectedException extends DbsUpdateServiceException
{
    public function __construct(EducationCandidate $candidate, public readonly string $resultType)
    {
        parent::__construct("DBS Update Service check was not successful: {$resultType}", $candidate);
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return array_merge(parent::context(), [
            'dbs_certificate_number' => $this->candidate?->dbs_certificate_number,
            'result_type' => $this->resultType,
        ]);
    }
}
