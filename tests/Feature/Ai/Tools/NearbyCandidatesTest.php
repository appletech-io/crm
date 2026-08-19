<?php

use App\Ai\Tools\NearbyCandidates;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('it finds candidates within the radius of a named client, ordered nearest first', function () {
    $client = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Riverside School',
        'postcode' => null,
        'latitude' => 52.4862,
        'longitude' => -1.8904,
    ]);

    $nearby = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Close',
        'latitude' => 52.4700,
        'longitude' => -1.9000,
    ]);
    $farAway = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Far',
        'latitude' => 51.5072,
        'longitude' => -0.1276,
    ]);
    $unlocated = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Nowhere',
        'latitude' => null,
        'longitude' => null,
    ]);

    $result = (new NearbyCandidates)->handle(new Request(['location' => 'Riverside', 'radius_miles' => 10]));

    expect($result)->toContain('Close')
        ->and($result)->not->toContain('Far')
        ->and($result)->not->toContain('Nowhere');

    $link = nearbyCandidateUrl($nearby);
    expect($result)->toContain("[Close {$nearby->last_name}]({$link})")
        ->and($result)->toContain('mi away');
});

test('it geocodes a free-text address when no client matches', function () {
    Http::fake([
        'maps.googleapis.com/*' => Http::response([
            'results' => [
                ['geometry' => ['location' => ['lat' => 52.4862, 'lng' => -1.8904]]],
            ],
            'status' => 'OK',
        ]),
    ]);

    $nearby = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'latitude' => 52.4700,
        'longitude' => -1.9000,
    ]);
    $farAway = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'latitude' => 51.5072,
        'longitude' => -0.1276,
    ]);

    $result = (new NearbyCandidates)->handle(new Request(['location' => 'Birmingham City Centre', 'radius_miles' => 10]));

    expect($result)->toContain(nearbyCandidateUrl($nearby))
        ->and($result)->not->toContain(nearbyCandidateUrl($farAway));
});

test('it defaults to a 10 mile radius when none is given', function () {
    $client = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Riverside School',
        'postcode' => null,
        'latitude' => 52.4862,
        'longitude' => -1.8904,
    ]);

    // ~2 miles away — inside the default radius.
    $nearby = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'latitude' => 52.4700,
        'longitude' => -1.9000,
    ]);

    $result = (new NearbyCandidates)->handle(new Request(['location' => 'Riverside']));

    expect($result)->toContain(nearbyCandidateUrl($nearby));
});

test('it reports when the location cannot be found', function () {
    Http::fake([
        'maps.googleapis.com/*' => Http::response(['results' => [], 'status' => 'ZERO_RESULTS']),
    ]);

    $result = (new NearbyCandidates)->handle(new Request(['location' => 'Nowhere In Particular']));

    expect($result)->toBe('Could not find a location matching "Nowhere In Particular".');
});

test('it reports when nothing is within the radius', function () {
    $client = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Riverside School',
        'postcode' => null,
        'latitude' => 52.4862,
        'longitude' => -1.8904,
    ]);

    EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'latitude' => 51.5072,
        'longitude' => -0.1276,
    ]);

    $result = (new NearbyCandidates)->handle(new Request(['location' => 'Riverside', 'radius_miles' => 5]));

    expect($result)->toBe('No candidates found within 5 miles of Riverside School.');
});

test('a consultant only sees their own candidates, while an admin sees everyone\'s', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    // consultant_id defaults to the currently-authenticated user, so the
    // client must be created as the consultant to be visible to them later.
    $this->actingAs($consultant);
    $client = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Riverside School',
        'postcode' => null,
        'latitude' => 52.4862,
        'longitude' => -1.8904,
    ]);
    $this->actingAs($this->user);

    $ownCandidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'consultant_id' => $consultant->id,
        'latitude' => 52.4700,
        'longitude' => -1.9000,
    ]);
    $othersCandidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'consultant_id' => $this->user->id,
        'latitude' => 52.4750,
        'longitude' => -1.9050,
    ]);

    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);

    $result = (new NearbyCandidates)->handle(new Request(['location' => 'Riverside', 'radius_miles' => 10]));

    expect($result)->toContain(nearbyCandidateUrl($ownCandidate))
        ->and($result)->not->toContain(nearbyCandidateUrl($othersCandidate));
});

test('it does not return a location match from a different industry', function () {
    $otherIndustry = Industry::factory()->create();
    Client::factory()->create([
        'industry_id' => $otherIndustry->id,
        'name' => 'Riverside School',
        'latitude' => 52.4862,
        'longitude' => -1.8904,
    ]);

    Http::fake([
        'maps.googleapis.com/*' => Http::response(['results' => [], 'status' => 'ZERO_RESULTS']),
    ]);

    $result = (new NearbyCandidates)->handle(new Request(['location' => 'Riverside']));

    expect($result)->toBe('Could not find a location matching "Riverside".');
});

function nearbyCandidateUrl(EducationCandidate $candidate): string
{
    return EducationCandidateResource::getUrl('edit', ['record' => $candidate]);
}
