<?php

namespace App\Actions\Applications;

use App\Actions\References\ResendReferenceRequestEmail;
use App\Jobs\SendApplicationEmail;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Manually re-sends the application email for a candidate whose application
 * link has expired, generating a fresh access token and expiry — mirrors
 * {@see ResendReferenceRequestEmail}.
 */
class ResendApplicationEmail
{
    use AsAction;

    public function handle(EducationCandidate|HealthcareCandidate $candidate): void
    {
        $application = $candidate->application;

        if (! $application) {
            return;
        }

        $application->update([
            'token' => (string) Str::uuid(),
            'expires_on' => now()->addWeeks(2)->toDateString(),
        ]);

        SendApplicationEmail::dispatch($candidate, $application, auth()->id());
    }
}
