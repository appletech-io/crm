<?php

namespace App\Actions\References;

use App\Actions\Applications\ApplicationCompleted;
use App\Enums\ReferenceStatus;
use App\Jobs\SendReferenceRequestEmail;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Emails every reference the candidate consented to and asked us to contact
 * now, generating a fresh access token per reference. Called once the
 * candidate's application is completed — see {@see ApplicationCompleted}
 * and its Healthcare twin.
 */
class SendReferenceRequestEmails
{
    use AsAction;

    public function handle(EducationCandidate|HealthcareCandidate $candidate): void
    {
        $references = $candidate->references()
            ->where('status', ReferenceStatus::Pending->value)
            ->where('contact_now', true)
            ->whereNotNull('email')
            ->get();

        foreach ($references as $reference) {
            $reference->update([
                'token' => (string) Str::uuid(),
                'expires_on' => now()->addDays(7)->toDateString(),
                'status' => ReferenceStatus::Contacted,
                'last_contacted' => now()->toDateString(),
            ]);

            SendReferenceRequestEmail::dispatch($reference);
        }
    }
}
