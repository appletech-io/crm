<?php

namespace App\Actions\Candidates;

use App\Models\CandidateStatusAutomation;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckCandidateStatusAutomations
{
    use AsAction;

    public function handle(Model $candidate): void
    {
        $currentStatusIds = $candidate->statuses()->pluck('candidate_status_id');

        if ($currentStatusIds->isEmpty()) {
            return;
        }

        CandidateStatusAutomation::query()
            ->whereIn('candidate_status_id', $currentStatusIds)
            ->get()
            ->each(function (CandidateStatusAutomation $automation) use ($candidate): bool {
                if (! $automation->isSatisfiedBy($candidate)) {
                    return true;
                }

                ChangeCandidateStatus::run($candidate, $automation);

                return false;
            });
    }
}
