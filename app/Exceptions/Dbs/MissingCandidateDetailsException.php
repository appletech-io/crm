<?php

namespace App\Exceptions\Dbs;

use App\Models\EducationCandidate;

class MissingCandidateDetailsException extends DbsUpdateServiceException
{
    public function __construct(EducationCandidate $candidate)
    {
        parent::__construct('Candidate is missing details required for a DBS Update Service check.', $candidate);
    }
}
