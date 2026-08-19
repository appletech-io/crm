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

class NearbyCandidates implements Tool
{
    use ResolvesLocation;

    private const DEFAULT_RADIUS_MILES = 10;

    public function description(): Stringable|string
    {
        return 'Find the current user\'s candidates (for the currently active sector) within a given radius of a '.
            'location — either a client name or a free-text address/postcode. Returns at most 20 matching '.
            'candidates ordered nearest first, with their name, status, and distance in miles — never compliance '.
            'or personal details.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'location' => $schema->string()->description('A client name, or an address/postcode, to search around')->required(),
            'radius_miles' => $schema->integer()->description('Radius in miles to search within. Defaults to 10 if not given.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $candidateModelClass = Industry::candidateModelForSlug(active_industry() ?? '');

        if (! $candidateModelClass) {
            return 'No active sector is selected, so nearby candidates cannot be searched right now.';
        }

        $location = $this->resolveLocation((string) $request['location']);

        if (! $location) {
            return "Could not find a location matching \"{$request['location']}\".";
        }

        $radiusMiles = $request->filled('radius_miles') ? (int) $request['radius_miles'] : self::DEFAULT_RADIUS_MILES;

        $candidates = $candidateModelClass::query()
            ->visibleToCurrentUser()
            ->select(['id', 'first_name', 'last_name', 'latitude', 'longitude'])
            ->with('latestStatus.status')
            ->tap(function (Builder $query) use ($location, $radiusMiles): void {
                $this->filterWithinRadius($query, $location['lat'], $location['lng'], $radiusMiles);
                $this->orderByDistance($query, $location['lat'], $location['lng']);
            })
            ->limit(20)
            ->get();

        if ($candidates->isEmpty()) {
            return "No candidates found within {$radiusMiles} miles of {$location['description']}.";
        }

        return $candidates
            ->map(function ($candidate) use ($location): string {
                $link = TodoLinkedRecord::candidateLink($candidate);
                $status = $candidate->latestStatus?->status?->name ?? 'No status';
                $miles = $this->distanceInMiles($location['lat'], $location['lng'], (float) $candidate->latitude, (float) $candidate->longitude);

                return "- [{$link['label']}]({$link['url']}) — {$status} — ".number_format($miles, 1).' mi away';
            })
            ->implode("\n");
    }
}
