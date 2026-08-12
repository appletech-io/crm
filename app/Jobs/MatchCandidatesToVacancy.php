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

    /**
     * Below this, a candidate isn't a serious enough fit to surface on the
     * Matches tab — weak matches would just add noise for a consultant
     * scanning the list.
     */
    private const MINIMUM_SCORE = 50;

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

        // Every run is a full re-match, not an incremental refresh — clearing
        // first means a candidate who's since left the pool (deleted, moved
        // company) or fallen below the minimum score can't leave a stale
        // match row behind that this run would otherwise never revisit.
        VacancyCandidateMatch::where('vacancy_id', $vacancy->id)->delete();

        $candidateModelClass::query()
            ->where('company_id', $vacancy->company_id)
            ->with(['skills', 'employmentHistories'])
            ->chunkById(100, function ($candidates) use ($vacancy, $matcher, $candidateModelClass): void {
                foreach ($candidates as $candidate) {
                    $score = $matcher->score($candidate, $vacancy);

                    if ($score === null || $score <= self::MINIMUM_SCORE) {
                        continue;
                    }

                    VacancyCandidateMatch::create([
                        'vacancy_id' => $vacancy->id,
                        'candidate_type' => $candidateModelClass,
                        'candidate_id' => $candidate->id,
                        'score' => $score,
                    ]);
                }
            });
    }
}
