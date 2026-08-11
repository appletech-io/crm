<?php

namespace App\Services\Matching;

use App\Models\Vacancy;
use App\Services\Matching\Contracts\MatchScorer;
use App\Services\Matching\Scorers\JobTitleMatchScorer;
use App\Services\Matching\Scorers\LocationProximityScorer;
use App\Services\Matching\Scorers\SkillMatchScorer;
use Illuminate\Database\Eloquent\Model;

/**
 * Scores how well a candidate fits a vacancy as a weighted average of
 * whichever scorers have a signal for that pair. New factors (qualification,
 * salary, availability, ...) can be added by implementing MatchScorer and
 * passing them in, without touching any of the call sites below.
 */
class CandidateMatcher
{
    /** @var array<int, MatchScorer> */
    private array $scorers;

    /** @param array<int, MatchScorer>|null $scorers */
    public function __construct(?array $scorers = null)
    {
        $this->scorers = $scorers ?? [
            new SkillMatchScorer,
            new JobTitleMatchScorer,
            new LocationProximityScorer,
        ];
    }

    /** Returns a 0–100 match score, or null if no scorer had a signal for this pair. */
    public function score(Model $candidate, Vacancy $vacancy): ?int
    {
        $weightedSum = 0.0;
        $totalWeight = 0.0;

        foreach ($this->scorers as $scorer) {
            $result = $scorer->score($candidate, $vacancy);

            if ($result === null) {
                continue;
            }

            $weightedSum += $result * $scorer->weight();
            $totalWeight += $scorer->weight();
        }

        if ($totalWeight === 0.0) {
            return null;
        }

        return (int) round(($weightedSum / $totalWeight) * 100);
    }
}
