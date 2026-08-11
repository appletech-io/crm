<?php

namespace App\Jobs;

use App\Models\Industry;
use App\Models\Vacancy;
use App\Models\VacancyCandidateMatch;
use App\Services\Matching\CandidateMatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MatchCandidatesToVacancy implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly int $vacancyId) {}

    public function handle(CandidateMatcher $matcher): void
    {
        $vacancy = Vacancy::with(['client', 'skills', 'jobTitle'])->find($this->vacancyId);

        if (! $vacancy || ! $vacancy->client) {
            return;
        }

        $candidateModelClass = Industry::candidateModelForSlug($vacancy->client->industry->slug);

        if (! $candidateModelClass) {
            return;
        }

        $candidateModelClass::query()
            ->where('company_id', $vacancy->company_id)
            ->with(['skills', 'employmentHistories'])
            ->chunkById(100, function ($candidates) use ($vacancy, $matcher, $candidateModelClass): void {
                foreach ($candidates as $candidate) {
                    $score = $matcher->score($candidate, $vacancy);

                    if ($score === null) {
                        continue;
                    }

                    VacancyCandidateMatch::updateOrCreate([
                        'vacancy_id' => $vacancy->id,
                        'candidate_type' => $candidateModelClass,
                        'candidate_id' => $candidate->id,
                    ], [
                        'score' => $score,
                    ]);
                }
            });
    }
}
