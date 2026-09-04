<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Wraps Google's Places API (New) with session-token billing and a short
 * cache. Without a session token, every autocomplete keystroke-pause and
 * the follow-up place-details call are billed as separate full-price
 * requests; with one, the whole search-to-selection session is billed as a
 * single Place Details charge instead — see
 * https://developers.google.com/maps/documentation/places/web-service/session-pricing.
 *
 * The cache is intentionally short-lived (a few minutes) — it exists only
 * to absorb duplicate/retyped queries during a live typing session, not to
 * build a standing address database from Google's data.
 */
class GooglePlacesService
{
    private const CACHE_TTL_MINUTES = 5;

    public function newSessionToken(): string
    {
        return (string) Str::uuid();
    }

    /** @return array<string, string> place_id => display text */
    public function autocomplete(string $input, string $sessionToken): array
    {
        $suggestions = Cache::remember(
            'google-places:autocomplete:'.md5($input),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($input, $sessionToken): array {
                $response = Http::withHeaders([
                    'X-Goog-Api-Key' => config('services.google.places_key'),
                    'X-Goog-FieldMask' => 'suggestions.placePrediction.placeId,suggestions.placePrediction.text',
                ])->post('https://places.googleapis.com/v1/places:autocomplete', [
                    'input' => $input,
                    'includedRegionCodes' => ['gb'],
                    'sessionToken' => $sessionToken,
                ]);

                return $response->failed() ? [] : ($response->json('suggestions') ?? []);
            }
        );

        return collect($suggestions)
            ->mapWithKeys(fn (array $s): array => [$s['placePrediction']['placeId'] => $s['placePrediction']['text']['text']])
            ->all();
    }

    /** @return array<string, mixed>|null */
    public function placeDetails(string $placeId, string $sessionToken): ?array
    {
        return Cache::remember(
            "google-places:details:{$placeId}",
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($placeId, $sessionToken): ?array {
                $response = Http::withHeaders([
                    'X-Goog-Api-Key' => config('services.google.places_key'),
                    'X-Goog-FieldMask' => 'addressComponents,formattedAddress',
                ])->get("https://places.googleapis.com/v1/places/{$placeId}", [
                    'sessionToken' => $sessionToken,
                ]);

                return $response->failed() ? null : $response->json();
            }
        );
    }
}
