<?php

namespace App\Services\Matching\Contracts;

use App\Models\Vacancy;
use Illuminate\Database\Eloquent\Model;

interface MatchScorer
{
    /**
     * Score how well the candidate fits the vacancy, normalized to 0.0–1.0.
     * Returns null when this scorer has no signal for the pair (e.g. one
     * side is missing the data it needs), rather than treating that as a
     * zero — an unscoreable factor shouldn't drag the overall match down.
     */
    public function score(Model $candidate, Vacancy $vacancy): ?float;

    /** How heavily this scorer's result counts in the weighted average. */
    public function weight(): float;
}
