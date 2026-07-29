<?php

namespace App\Actions\References;

use App\Enums\ReferenceStatus;
use App\Jobs\SendReferenceRequestEmail;
use App\Models\CandidateReference;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Manually re-sends the reference request email for a single reference,
 * generating a fresh access token and expiry — used when a consultant wants
 * to prompt a referee again, unlike {@see SendReferenceRequestEmails} which
 * only sends first-time requests to still-pending references.
 */
class ResendReferenceRequestEmail
{
    use AsAction;

    public function handle(CandidateReference $reference): void
    {
        if (! $reference->contact_now || blank($reference->email)) {
            return;
        }

        $reference->update([
            'token' => (string) Str::uuid(),
            'expires_on' => now()->addDays(7)->toDateString(),
            'status' => ReferenceStatus::Contacted,
            'last_contacted' => now()->toDateString(),
        ]);

        SendReferenceRequestEmail::dispatch($reference);
    }
}
