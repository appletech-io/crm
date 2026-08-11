<?php

namespace App\Services\Matching\Scorers;

use App\Models\Vacancy;
use App\Services\Matching\Contracts\MatchScorer;
use Illuminate\Database\Eloquent\Model;

/**
 * What fraction of the vacancy's required skills the candidate has.
 * Skills are compared by ID directly rather than expanding parent/child
 * hierarchy: attaching a child skill to a candidate already attaches its
 * parent too (see the vacancy apply form), so a candidate with a specific
 * skill already carries the broader one — the reverse (crediting a general
 * skill for a specific requirement) is intentionally not assumed.
 */
class SkillMatchScorer implements MatchScorer
{
    public function weight(): float
    {
        return 0.5;
    }

    public function score(Model $candidate, Vacancy $vacancy): ?float
    {
        $requiredSkillIds = $vacancy->skills->pluck('id');

        if ($requiredSkillIds->isEmpty()) {
            return null;
        }

        $candidateSkillIds = $candidate->skills->pluck('id');

        $matched = $requiredSkillIds->intersect($candidateSkillIds)->count();

        return $matched / $requiredSkillIds->count();
    }
}
