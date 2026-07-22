<?php

namespace App\Console\Commands;

use App\Actions\Candidates\CheckCandidateStatusAutomations;
use App\Models\CandidateStatusAutomation;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

#[Signature('automations:check-time-based')]
#[Description('Re-evaluate candidate status automations whose conditions depend on elapsed time, since nothing else triggers a re-check for them')]
class CheckTimeBasedStatusAutomations extends Command
{
    /** @var array<int, class-string<Model>> */
    private const CANDIDATE_MODELS = [
        EducationCandidate::class,
        HealthcareCandidate::class,
    ];

    public function handle(): int
    {
        $statusIds = CandidateStatusAutomation::query()
            ->get()
            ->filter(fn (CandidateStatusAutomation $automation): bool => collect($automation->conditions)
                ->contains(fn (array $condition): bool => ($condition['operator'] ?? null) === 'days_since_at_least')
            )
            ->pluck('candidate_status_id')
            ->unique();

        if ($statusIds->isEmpty()) {
            return self::SUCCESS;
        }

        foreach (self::CANDIDATE_MODELS as $candidateModel) {
            $candidateModel::query()
                ->whereHas('statuses', fn ($query) => $query->whereIn('candidate_status_id', $statusIds))
                ->chunkById(100, function ($candidates): void {
                    $candidates->each(fn (Model $candidate) => CheckCandidateStatusAutomations::run($candidate));
                });
        }

        return self::SUCCESS;
    }
}
