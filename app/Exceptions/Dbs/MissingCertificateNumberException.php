<?php

namespace App\Exceptions\Dbs;

use App\Models\EducationCandidate;

class MissingCertificateNumberException extends DbsUpdateServiceException
{
    public function __construct(EducationCandidate $candidate)
    {
        parent::__construct('Candidate does not have a DBS certificate number to check.', $candidate);
    }
}
