<?php

use App\Services\GooglePlacesService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    Cache::flush();
});

test('newSessionToken returns a UUID', function () {
    $token = app(GooglePlacesService::class)->newSessionToken();

    expect(Str::isUuid($token))->toBeTrue();
});

test('autocomplete sends the session token in the request body', function () {
    Http::fake([
        'places.googleapis.com/v1/places:autocomplete' => Http::response([
            'suggestions' => [
                ['placePrediction' => ['placeId' => 'place-1', 'text' => ['text' => '10 Downing Street, London, UK']]],
            ],
        ], 200),
    ]);

    app(GooglePlacesService::class)->autocomplete('10 Downing', 'session-token-abc');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'places:autocomplete')
        && $request['input'] === '10 Downing'
        && $request['sessionToken'] === 'session-token-abc');
});

test('autocomplete caches identical queries instead of re-hitting the api', function () {
    Http::fake([
        'places.googleapis.com/v1/places:autocomplete' => Http::response([
            'suggestions' => [
                ['placePrediction' => ['placeId' => 'place-1', 'text' => ['text' => '10 Downing Street, London, UK']]],
            ],
        ], 200),
    ]);

    $service = app(GooglePlacesService::class);
    $first = $service->autocomplete('10 Downing', 'session-a');
    $second = $service->autocomplete('10 Downing', 'session-b');

    expect($first)->toBe($second);
    Http::assertSentCount(1);
});

test('placeDetails sends the session token as a query parameter', function () {
    Http::fake([
        'places.googleapis.com/v1/places/place-1*' => Http::response([
            'formattedAddress' => '10 Downing St, London SW1A 2AA, UK',
            'addressComponents' => [],
        ], 200),
    ]);

    app(GooglePlacesService::class)->placeDetails('place-1', 'session-token-abc');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'places/place-1')
        && str_contains($request->url(), 'sessionToken=session-token-abc'));
});

test('placeDetails caches by place id instead of re-hitting the api', function () {
    Http::fake([
        'places.googleapis.com/v1/places/place-1*' => Http::response([
            'formattedAddress' => '10 Downing St, London SW1A 2AA, UK',
            'addressComponents' => [],
        ], 200),
    ]);

    $service = app(GooglePlacesService::class);
    $first = $service->placeDetails('place-1', 'session-a');
    $second = $service->placeDetails('place-1', 'session-b');

    expect($first)->toBe($second);
    Http::assertSentCount(1);
});

test('placeDetails returns null when the api call fails', function () {
    Http::fake([
        'places.googleapis.com/v1/places/place-1*' => Http::response([], 404),
    ]);

    expect(app(GooglePlacesService::class)->placeDetails('place-1', 'session-a'))->toBeNull();
});
