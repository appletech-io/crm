<?php

namespace App\Ai\Tools\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Matches a free-text name (e.g. a full "First Last" string from a chat
 * prompt) against separate first_name/last_name columns. A plain LIKE
 * against either column alone fails for a two-word name, since neither
 * column contains the full string — this requires every word in the
 * search term to appear in at least one of the two columns instead.
 */
trait MatchesCandidateName
{
    private function whereNameContains(Builder $query, string $name): Builder
    {
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        return $query->where(function ($q) use ($words) {
            foreach ($words as $word) {
                $q->where(
                    fn ($qq) => $qq->where('first_name', 'like', '%'.$word.'%')
                        ->orWhere('last_name', 'like', '%'.$word.'%')
                );
            }
        });
    }
}
