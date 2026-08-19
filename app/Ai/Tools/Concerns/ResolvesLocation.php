<?php

namespace App\Ai\Tools\Concerns;

use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;

/**
 * Resolves a free-text location the same way the candidate Search tab does —
 * a known client's stored coordinates first, otherwise a fresh Google
 * geocode of the address/postcode — then filters/orders candidates by
 * straight line distance using the same flat-earth SQL approximation as
 * CandidateSearchService::applyLocation() (literal-interpolated floats, not
 * bound params, for the same SQLite/PDO reason documented there).
 */
trait ResolvesLocation
{
    /** @return ?array{lat: float, lng: float, description: string} */
    private function resolveLocation(string $location): ?array
    {
        $client = Client::query()
            ->visibleToCurrentUser()
            ->where('industry_id', active_industry_id())
            ->where('name', 'like', '%'.$location.'%')
            ->first();

        if ($client && $client->latitude !== null && $client->longitude !== null) {
            return ['lat' => (float) $client->latitude, 'lng' => (float) $client->longitude, 'description' => $client->name];
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $location,
            'key' => config('services.google.places_key'),
        ]);

        $result = $response->successful() ? $response->json('results.0.geometry.location') : null;

        if (! $result) {
            return null;
        }

        return ['lat' => (float) $result['lat'], 'lng' => (float) $result['lng'], 'description' => $location];
    }

    private function filterWithinRadius(Builder $query, float $lat, float $lng, float $radiusMiles): void
    {
        $query->whereRaw('('.$this->squaredDistanceExpression($lat, $lng).') <= '.$this->literal($radiusMiles ** 2));
    }

    private function orderByDistance(Builder $query, float $lat, float $lng): void
    {
        $query->orderByRaw($this->squaredDistanceExpression($lat, $lng));
    }

    private function squaredDistanceExpression(float $lat, float $lng): string
    {
        $milesPerDegreeLat = 69.0;
        $milesPerDegreeLng = 69.0 * max(cos(deg2rad($lat)), 0.01);

        $latLiteral = $this->literal($lat);
        $lngLiteral = $this->literal($lng);
        $milesPerDegreeLatLiteral = $this->literal($milesPerDegreeLat);
        $milesPerDegreeLngLiteral = $this->literal($milesPerDegreeLng);

        return "((latitude - {$latLiteral}) * {$milesPerDegreeLatLiteral} * (latitude - {$latLiteral}) * {$milesPerDegreeLatLiteral}) + ".
            "((longitude - {$lngLiteral}) * {$milesPerDegreeLngLiteral} * (longitude - {$lngLiteral}) * {$milesPerDegreeLngLiteral})";
    }

    private function literal(float $value): string
    {
        return sprintf('%.10F', $value);
    }

    private function distanceInMiles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusMiles = 3959;

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadiusMiles * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
