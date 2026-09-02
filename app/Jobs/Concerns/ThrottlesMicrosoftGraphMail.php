<?php

namespace App\Jobs\Concerns;

use App\Models\Company;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Shared by every job that sends mail through Microsoft Graph. Spreads a
 * burst of queued sends out over time instead of firing them all at once —
 * that burst, not request count alone, is what trips Graph's
 * "ApplicationThrottled: IncomingBytes" limit (each send carries a base64
 * logo attachment, so 200 queued emails can multiply into a lot of bytes in
 * a short window). The limiter itself is registered once, in
 * AppServiceProvider, keyed per company.
 */
trait ThrottlesMicrosoftGraphMail
{
    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new RateLimited('microsoft-graph-mail')];
    }

    /**
     * The company whose Graph app registration this job's email will send
     * through — null if the job can't resolve one (e.g. a deleted relation),
     * in which case the limiter applies no throttling at all.
     */
    abstract public function graphMailCompany(): ?Company;
}
