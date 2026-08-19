<?php

namespace App\Jobs;

use App\Models\HealthcareCandidate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodeHealthcareCandidate implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly HealthcareCandidate $candidate) {}

    public function handle(): void
    {
        $postcode = $this->candidate->postcode;

        if (blank($postcode)) {
            return;
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $postcode,
            'key' => config('services.google.places_key'),
        ]);

        if (! $response->successful()) {
            Log::warning('Geocoding request failed', ['candidate_id' => $this->candidate->id, 'postcode' => $postcode]);

            return;
        }

        $result = $response->json('results.0.geometry.location');

        if (! $result) {
            Log::warning('Geocoding returned no results', [
                'candidate_id' => $this->candidate->id,
                'postcode' => $postcode,
                'google_status' => $response->json('status'),
                'google_error' => $response->json('error_message'),
            ]);

            return;
        }

        $this->candidate->updateQuietly([
            'latitude' => $result['lat'],
            'longitude' => $result['lng'],
        ]);
    }
}
