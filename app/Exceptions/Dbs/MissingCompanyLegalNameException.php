<?php

namespace App\Exceptions\Dbs;

use App\Models\EducationCandidate;

class MissingCompanyLegalNameException extends DbsUpdateServiceException
{
    public function __construct(EducationCandidate $candidate)
    {
        parent::__construct("Candidate's company has no legal name set for a DBS Update Service check.", $candidate);
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return array_merge(parent::context(), [
            'company_id' => $this->candidate?->company_id,
        ]);
    }
}
