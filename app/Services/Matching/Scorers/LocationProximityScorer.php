<?php

namespace App\Services\Matching\Scorers;

use App\Models\Vacancy;
use App\Services\Education\CandidateSearchService;
use App\Services\Matching\Contracts\MatchScorer;
use Illuminate\Database\Eloquent\Model;

/**
 * How close the candidate lives to the vacancy's workplace (the client's
 * address). Full score within a comfortable commute, decaying to nothing
 * by the point a commute becomes impractical.
 */
class LocationProximityScorer implements MatchScorer
{
    private const FULL_SCORE_MILES = 5.0;

    private const ZERO_SCORE_MILES = 30.0;

    public function weight(): float
    {
        return 0.2;
    }

    public function score(Model $candidate, Vacancy $vacancy): ?float
    {
        $clientLat = $vacancy->client?->latitude;
        $clientLng = $vacancy->client?->longitude;
        $candidateLat = $candidate->latitude;
        $candidateLng = $candidate->longitude;

        if ($clientLat === null || $clientLng === null || $candidateLat === null || $candidateLng === null) {
            return null;
        }

        $miles = CandidateSearchService::distanceInMiles($candidateLat, $candidateLng, $clientLat, $clientLng);

        if ($miles <= self::FULL_SCORE_MILES) {
            return 1.0;
        }

        if ($miles >= self::ZERO_SCORE_MILES) {
            return 0.0;
        }

        return 1.0 - (($miles - self::FULL_SCORE_MILES) / (self::ZERO_SCORE_MILES - self::FULL_SCORE_MILES));
    }
}
