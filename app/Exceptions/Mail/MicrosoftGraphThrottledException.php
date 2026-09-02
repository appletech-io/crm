<?php

namespace App\Exceptions\Mail;

use RuntimeException;

/**
 * Thrown when Microsoft Graph responds 429 "ApplicationThrottled" — carries
 * the Retry-After Graph itself reported, so the calling job can release
 * itself back onto the queue for exactly that long instead of guessing at a
 * flat backoff (or worse, retrying immediately and re-tripping the same
 * throttle).
 */
class MicrosoftGraphThrottledException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Microsoft Graph throttled this request.');
    }
}
