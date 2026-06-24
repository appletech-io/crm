<?php

use App\Jobs\GeocodeEducationCandidate;
use App\Models\EducationCandidate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('geocoding job is dispatched when postcode is set on create', function () {
    Queue::fake();

    $candidate = EducationCandidate::factory()->create(['postcode' => 'SW1A 1AA']);

    Queue::assertPushed(GeocodeEducationCandidate::class, fn ($job) => $job->candidate->is($candidate));
});

test('geocoding job is dispatched when postcode changes', function () {
    Queue::fake();

    $candidate = EducationCandidate::factory()->create(['postcode' => null]);

    Queue::assertNotPushed(GeocodeEducationCandidate::class);

    $candidate->update(['postcode' => 'SW1A 1AA']);

    Queue::assertPushed(GeocodeEducationCandidate::class, fn ($job) => $job->candidate->is($candidate));
});

test('geocoding job is not dispatched when postcode is unchanged', function () {
    Queue::fake();

    $candidate = EducationCandidate::factory()->create(['postcode' => null]);
    $candidate->update(['first_name' => 'Jane']);

    Queue::assertNotPushed(GeocodeEducationCandidate::class);
});

test('geocoding job stores latitude and longitude from google response', function () {
    Http::fake([
        'maps.googleapis.com/*' => Http::response([
            'results' => [
                [
                    'geometry' => [
                        'location' => [
                            'lat' => 51.50153,
                            'lng' => -0.14158,
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $candidate = EducationCandidate::factory()->create(['postcode' => 'SW1A 1AA']);

    (new GeocodeEducationCandidate($candidate))->handle();

    expect($candidate->refresh())
        ->latitude->toEqual(51.50153)
        ->longitude->toEqual(-0.14158);
});

test('geocoding job handles a failed google response gracefully', function () {
    Http::fake([
        'maps.googleapis.com/*' => Http::response([], 500),
    ]);

    $candidate = EducationCandidate::factory()->create(['postcode' => 'SW1A 1AA', 'latitude' => null, 'longitude' => null]);

    (new GeocodeEducationCandidate($candidate))->handle();

    expect($candidate->refresh())
        ->latitude->toBeNull()
        ->longitude->toBeNull();
});
