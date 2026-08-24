<?php

namespace App\Services\Payroll\Exceptions;

use RuntimeException;

class PayrollProviderException extends RuntimeException
{
    /** @param  array<int, string>  $errors */
    public function __construct(string $message, public readonly array $errors)
    {
        parent::__construct($message);
    }
}
