<?php

namespace App\Actions\Candidates;

use App\Jobs\SendApplicationEmail;
use App\Models\Candidate;
use App\Models\CandidateStatus;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class GenericCandidateCreated
{
    use AsAction;

    /**
     * Unlike Education/Healthcare, a generic Candidate's required documents
     * and compliance actions are resolved from job_title_id (see
     * ComplianceRequirements) rather than a fixed set of vetting fields —
     * without one there's nothing for the candidate's portal to show, so
     * there's no application to send until staff assign a job title.
     */
    public function handle(Candidate $candidate, bool $sync = false): void
    {
        if (! $candidate->job_title_id) {
            return;
        }

        $application = $candidate->application()->updateOrCreate([], [
            'company_id' => $candidate->company_id,
            'job_title_id' => $candidate->job_title_id,
            'status' => 'pending',
            'token' => Str::uuid(),
            'expires_on' => now()->addWeeks(2)->toDateString(),
        ]);

        $onboarding = CandidateStatus::where('company_id', $candidate->company_id)
            ->where('industry_id', $candidate->industry_id)
            ->where('name', 'Onboarding')
            ->first();

        if ($onboarding) {
            $candidate->statuses()->firstOrCreate(['candidate_status_id' => $onboarding->id]);
        }

        if ($sync) {
            SendApplicationEmail::dispatchSync($candidate, $application, auth()->id());
        } else {
            SendApplicationEmail::dispatch($candidate, $application, auth()->id());
        }
    }
}
