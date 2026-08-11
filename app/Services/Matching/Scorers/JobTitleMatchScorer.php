<?php

namespace App\Services\Matching\Scorers;

use App\Models\CandidateEmploymentHistory;
use App\Models\Vacancy;
use App\Services\Matching\Contracts\MatchScorer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * How closely the candidate's most recent job title matches the vacancy's.
 * There's no structured link between free-text employment history and the
 * JobTitle lookup table, so this compares words rather than exact strings —
 * "Year 3 Class Teacher" and "Class Teacher" share enough to count as a
 * real signal without requiring an exact match.
 */
class JobTitleMatchScorer implements MatchScorer
{
    public function weight(): float
    {
        return 0.3;
    }

    public function score(Model $candidate, Vacancy $vacancy): ?float
    {
        $vacancyTitle = $vacancy->jobTitle?->name;

        if (blank($vacancyTitle)) {
            return null;
        }

        $candidateTitle = $this->mostRecentJobTitle($candidate);

        if (blank($candidateTitle)) {
            return null;
        }

        $vacancyWords = $this->wordsFor($vacancyTitle);
        $candidateWords = $this->wordsFor($candidateTitle);

        if ($vacancyWords->isEmpty() || $candidateWords->isEmpty()) {
            return null;
        }

        $intersection = $vacancyWords->intersect($candidateWords)->count();
        $union = $vacancyWords->merge($candidateWords)->unique()->count();

        return $intersection / $union;
    }

    private function mostRecentJobTitle(Model $candidate): ?string
    {
        // A null worked_to means the role is still current, which makes it
        // the most recent one regardless of when it started — not a
        // fallback to worked_from, which could make an ongoing role look
        // older than one that's already finished.
        return $candidate->employmentHistories
            ->sortByDesc(fn (CandidateEmploymentHistory $history) => $history->worked_to ?? now())
            ->pluck('job_title')
            ->first(fn (?string $title): bool => filled($title));
    }

    /** @return Collection<int, non-empty-string> */
    private function wordsFor(string $title): Collection
    {
        $words = preg_split('/[^a-z0-9]+/', strtolower($title), -1, PREG_SPLIT_NO_EMPTY);

        return collect($words === false ? [] : $words);
    }
}
