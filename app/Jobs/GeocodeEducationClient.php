<?php

namespace App\Jobs;

use App\Models\EducationClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodeEducationClient implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly EducationClient $client) {}

    public function handle(): void
    {
        $postcode = $this->client->postcode;

        if (blank($postcode)) {
            return;
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $postcode,
            'key' => config('services.google.places_key'),
        ]);

        if (! $response->successful()) {
            Log::warning('Geocoding request failed', ['client_id' => $this->client->id, 'postcode' => $postcode]);

            return;
        }

        $result = $response->json('results.0.geometry.location');

        if (! $result) {
            Log::warning('Geocoding returned no results', [
                'client_id' => $this->client->id,
                'postcode' => $postcode,
                'google_status' => $response->json('status'),
                'google_error' => $response->json('error_message'),
            ]);

            return;
        }

        $this->client->updateQuietly([
            'latitude' => $result['lat'],
            'longitude' => $result['lng'],
        ]);
    }
}
