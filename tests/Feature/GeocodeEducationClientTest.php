<?php

use App\Jobs\GeocodeEducationClient;
use App\Models\EducationClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('geocoding job is dispatched when postcode is set on create', function () {
    Queue::fake();

    $client = EducationClient::factory()->create(['postcode' => 'SW1A 1AA']);

    Queue::assertPushed(GeocodeEducationClient::class, fn ($job) => $job->client->is($client));
});

test('geocoding job is dispatched when postcode changes', function () {
    Queue::fake();

    $client = EducationClient::factory()->create(['postcode' => null]);

    Queue::assertNotPushed(GeocodeEducationClient::class);

    $client->update(['postcode' => 'SW1A 1AA']);

    Queue::assertPushed(GeocodeEducationClient::class, fn ($job) => $job->client->is($client));
});

test('geocoding job is not dispatched when postcode is unchanged', function () {
    Queue::fake();

    $client = EducationClient::factory()->create(['postcode' => null]);
    $client->update(['name' => 'Updated Name']);

    Queue::assertNotPushed(GeocodeEducationClient::class);
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

    $client = EducationClient::factory()->create(['postcode' => 'SW1A 1AA']);

    (new GeocodeEducationClient($client))->handle();

    expect($client->refresh())
        ->latitude->toEqual(51.50153)
        ->longitude->toEqual(-0.14158);
});

test('geocoding job handles a failed google response gracefully', function () {
    Http::fake([
        'maps.googleapis.com/*' => Http::response([], 500),
    ]);

    $client = EducationClient::factory()->create(['postcode' => 'SW1A 1AA', 'latitude' => null, 'longitude' => null]);

    (new GeocodeEducationClient($client))->handle();

    expect($client->refresh())
        ->latitude->toBeNull()
        ->longitude->toBeNull();
});
