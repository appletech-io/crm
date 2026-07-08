<?php

namespace App\Exceptions\Dbs;

use App\Models\EducationCandidate;
use RuntimeException;

abstract class DbsUpdateServiceException extends RuntimeException
{
    public function __construct(string $message, protected readonly ?EducationCandidate $candidate = null)
    {
        parent::__construct($message);
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'candidate_id' => $this->candidate?->id,
        ];
    }
}
