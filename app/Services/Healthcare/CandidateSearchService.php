<?php

namespace App\Services\Healthcare;

use App\Enums\CandidateAvailabilityStatus;
use App\Models\HealthcareCandidate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CandidateSearchService
{
    /** @var array<int, string> Carbon ISO-8601 weekday numbers (1 = Monday) for the days this search covers. */
    public const array WEEKDAYS = [1, 2, 3, 4, 5];

    /**
     * @param  array{
     *     name?: ?string,
     *     email?: ?string,
     *     skill_ids?: ?array<int, int|string>,
     *     days?: ?array<int, int|string>,
     *     lat?: ?float,
     *     lng?: ?float,
     *     radius_miles?: ?float,
     * }  $filters
     */
    public function search(array $filters): Builder
    {
        $query = HealthcareCandidate::query()
            ->where('consultant_id', auth()->id())
            ->whereHas('statuses.status', fn (Builder $q) => $q->where('name', 'Live'));

        $this->applyName($query, $filters['name'] ?? null);
        $this->applyEmail($query, $filters['email'] ?? null);
        $this->applySkills($query, $filters['skill_ids'] ?? null);
        $this->applyDays($query, $filters['days'] ?? null);
        $this->applyLocation($query, $filters['lat'] ?? null, $filters['lng'] ?? null, $filters['radius_miles'] ?? null);

        return $query;
    }

    private function applyName(Builder $query, ?string $name): void
    {
        if (blank($name)) {
            return;
        }

        $query->where(fn (Builder $q) => $q
            ->where('first_name', 'like', "%{$name}%")
            ->orWhere('last_name', 'like', "%{$name}%")
        );
    }

    private function applyEmail(Builder $query, ?string $email): void
    {
        if (blank($email)) {
            return;
        }

        $query->where('email', 'like', "%{$email}%");
    }

    /** @param  ?array<int, int|string>  $skillIds */
    private function applySkills(Builder $query, ?array $skillIds): void
    {
        if (blank($skillIds)) {
            return;
        }

        $query->whereHas('skills', fn (Builder $q) => $q
            ->whereIn('candidate_skill_candidates.candidate_skill_id', $skillIds));
    }

    /**
     * A candidate is excluded for a searched day if they already have an
     * active booking that day, or have explicitly marked themselves Not
     * Available. A candidate with no availability recorded at all for that
     * day is still included — no data isn't the same as unavailable.
     *
     * @param  ?array<int, int|string>  $days  ISO-8601 weekday numbers (1 = Monday .. 5 = Friday)
     */
    private function applyDays(Builder $query, ?array $days): void
    {
        if (blank($days)) {
            return;
        }

        foreach ($this->datesForWeekdays($days) as $date) {
            $query->whereDoesntHave('bookings', fn (Builder $q) => $q
                ->whereHas('dayPeriods', fn (Builder $q2) => $q2
                    ->whereDate('date', $date)
                    ->whereNull('cancelled_at')));

            $query->whereDoesntHave('availabilities', fn (Builder $q) => $q
                ->whereDate('date', $date)
                ->where('status', CandidateAvailabilityStatus::NotAvailable->value));
        }
    }

    private function applyLocation(Builder $query, ?float $lat, ?float $lng, ?float $radiusMiles): void
    {
        if ($lat === null || $lng === null || blank($radiusMiles)) {
            return;
        }

        $milesPerDegreeLat = 69.0;
        $milesPerDegreeLng = 69.0 * max(cos(deg2rad($lat)), 0.01);

        // These are inlined as SQL literals rather than bound parameters
        // because SQLite's PDO driver (used by the test suite) doesn't
        // reliably filter a WHERE comparison against a bound parameter when
        // the other side is a computed expression — the same comparison
        // against a literal value, or the same bound value used only in a
        // SELECT, both work correctly. This is safe because every value here
        // is a native PHP float (enforced by this method's type hints), not
        // a raw string, so there's no injection risk.
        $lat = $this->literal($lat);
        $lng = $this->literal($lng);
        $milesPerDegreeLat = $this->literal($milesPerDegreeLat);
        $milesPerDegreeLng = $this->literal($milesPerDegreeLng);
        $radiusMilesSquared = $this->literal($radiusMiles ** 2);

        $squaredDistanceExpression = "((latitude - {$lat}) * {$milesPerDegreeLat} * (latitude - {$lat}) * {$milesPerDegreeLat}) + ((longitude - {$lng}) * {$milesPerDegreeLng} * (longitude - {$lng}) * {$milesPerDegreeLng})";

        $query->whereRaw("({$squaredDistanceExpression}) <= {$radiusMilesSquared}")
            ->orderByRaw($squaredDistanceExpression);
    }

    private function literal(float $value): string
    {
        return sprintf('%.10F', $value);
    }

    /**
     * @param  array<int, int|string>  $weekdays  ISO-8601 weekday numbers (1 = Monday .. 5 = Friday)
     * @return array<int, string>
     */
    private function datesForWeekdays(array $weekdays): array
    {
        $weekStart = now()->startOfWeek(Carbon::MONDAY);

        return collect($weekdays)
            ->map(fn (int|string $weekday): string => $weekStart->copy()->addDays(((int) $weekday) - 1)->toDateString())
            ->all();
    }

    /**
     * Exact great-circle distance in miles, for display only (not used for
     * filtering/sorting — see applyLocation()'s portable SQL approximation).
     */
    public static function distanceInMiles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusMiles = 3959;

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadiusMiles * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
