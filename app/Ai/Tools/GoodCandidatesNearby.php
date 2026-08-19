<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesLocation;
use App\Filament\Support\TodoLinkedRecord;
use App\Models\Industry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Ranks by the candidate's stored average_rating (past booking performance)
 * rather than the Vacancy-coupled matching system (CandidateMatcher /
 * VacancyCandidateMatch) — that scoring reads $vacancy->skills,
 * $vacancy->jobTitle, and $vacancy->client's coordinates directly off a
 * real, persisted Vacancy row, so it isn't a fit for an ad-hoc "good X near
 * Y" query that has no vacancy behind it.
 */
class GoodCandidatesNearby implements Tool
{
    use ResolvesLocation;

    private const DEFAULT_RADIUS_MILES = 10;

    public function description(): Stringable|string
    {
        return 'Find the current user\'s best-rated candidates (for the currently active sector) matching a '.
            'qualification or skill, within a given radius of a location — either a client name or a free-text '.
            'address/postcode. Ranks by each candidate\'s average booking rating, highest first — this reflects '.
            'past performance, not a vacancy match score. Returns at most 20 candidates with their name, status, '.
            'qualification, rating, and distance in miles — never compliance or personal details.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'location' => $schema->string()->description('A client name, or an address/postcode, to search around')->required(),
            'qualification_or_skill' => $schema->string()->description('Match candidates whose qualification or skill contains this text, e.g. "maths" or "Nursing"'),
            'radius_miles' => $schema->integer()->description('Radius in miles to search within. Defaults to 10 if not given.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $candidateModelClass = Industry::candidateModelForSlug(active_industry() ?? '');

        if (! $candidateModelClass) {
            return 'No active sector is selected, so candidates cannot be searched right now.';
        }

        $location = $this->resolveLocation((string) $request['location']);

        if (! $location) {
            return "Could not find a location matching \"{$request['location']}\".";
        }

        $radiusMiles = $request->filled('radius_miles') ? (int) $request['radius_miles'] : self::DEFAULT_RADIUS_MILES;
        $term = $request->filled('qualification_or_skill') ? (string) $request['qualification_or_skill'] : null;

        $query = $candidateModelClass::query()
            ->visibleToCurrentUser()
            ->select(['id', 'first_name', 'last_name', 'latitude', 'longitude', 'average_rating', 'ratings_count'])
            ->with(['qualification', 'latestStatus.status'])
            ->when($term, fn (Builder $q) => $q->where(
                fn (Builder $q2) => $q2->whereHas('qualification', fn (Builder $q3) => $q3->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('skills', fn (Builder $q3) => $q3->where('name', 'like', "%{$term}%"))
            ));

        $this->filterWithinRadius($query, $location['lat'], $location['lng'], $radiusMiles);
        $query->orderByDesc('average_rating');
        $this->orderByDistance($query, $location['lat'], $location['lng']);

        $candidates = $query->limit(20)->get();

        if ($candidates->isEmpty()) {
            $termSuffix = $term ? " matching \"{$term}\"" : '';

            return "No candidates found within {$radiusMiles} miles of {$location['description']}{$termSuffix}.";
        }

        return $candidates
            ->map(function ($candidate) use ($location): string {
                $link = TodoLinkedRecord::candidateLink($candidate);
                $status = $candidate->latestStatus?->status?->name ?? 'No status';
                $qualification = $candidate->qualification?->name ?? 'No qualification set';
                $rating = $candidate->average_rating !== null
                    ? number_format($candidate->average_rating, 1)." ★ ({$candidate->ratings_count})"
                    : 'Not yet rated';
                $miles = $this->distanceInMiles($location['lat'], $location['lng'], (float) $candidate->latitude, (float) $candidate->longitude);

                return "- [{$link['label']}]({$link['url']}) — {$status} — {$qualification} — {$rating} — ".number_format($miles, 1).' mi away';
            })
            ->implode("\n");
    }
}
