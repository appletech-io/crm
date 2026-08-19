<?php

use App\Ai\Tools\GoodCandidatesNearby;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Models\CandidateSkill;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\Qualification;
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

    $this->client = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Riverside School',
        'postcode' => null,
        'latitude' => 52.4862,
        'longitude' => -1.8904,
    ]);
});

function goodNearbyUrl(EducationCandidate $candidate): string
{
    return EducationCandidateResource::getUrl('edit', ['record' => $candidate]);
}

test('it ranks candidates within the radius by average rating, highest first', function () {
    $highlyRated = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'latitude' => 52.4700,
        'longitude' => -1.9000,
        'average_rating' => 4.8,
        'ratings_count' => 5,
    ]);
    $lowerRated = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'latitude' => 52.4750,
        'longitude' => -1.9050,
        'average_rating' => 3.2,
        'ratings_count' => 2,
    ]);
    $farAway = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'latitude' => 51.5072,
        'longitude' => -0.1276,
        'average_rating' => 5.0,
        'ratings_count' => 10,
    ]);

    $result = (new GoodCandidatesNearby)->handle(new Request(['location' => 'Riverside', 'radius_miles' => 10]));

    $lines = explode("\n", $result);

    expect($lines[0])->toContain(goodNearbyUrl($highlyRated))->toContain('4.8 ★ (5)')
        ->and($lines[1])->toContain(goodNearbyUrl($lowerRated))
        ->and($result)->not->toContain(goodNearbyUrl($farAway));
});

test('an unrated candidate is still included, ranked behind rated ones', function () {
    $rated = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'latitude' => 52.4700,
        'longitude' => -1.9000,
        'average_rating' => 3.0,
        'ratings_count' => 1,
    ]);
    $unrated = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'latitude' => 52.4750,
        'longitude' => -1.9050,
        'average_rating' => null,
        'ratings_count' => 0,
    ]);

    $result = (new GoodCandidatesNearby)->handle(new Request(['location' => 'Riverside', 'radius_miles' => 10]));

    $lines = explode("\n", $result);

    expect($lines[0])->toContain(goodNearbyUrl($rated))
        ->and($lines[1])->toContain(goodNearbyUrl($unrated))->toContain('Not yet rated');
});

test('the qualification_or_skill filter matches by qualification', function () {
    $maths = Qualification::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Maths',
    ]);
    $english = Qualification::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'English',
    ]);

    $mathsTeacher = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'latitude' => 52.4700,
        'longitude' => -1.9000,
        'qualification_id' => $maths->id,
    ]);
    $englishTeacher = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'latitude' => 52.4750,
        'longitude' => -1.9050,
        'qualification_id' => $english->id,
    ]);

    $result = (new GoodCandidatesNearby)->handle(new Request([
        'location' => 'Riverside',
        'radius_miles' => 10,
        'qualification_or_skill' => 'maths',
    ]));

    expect($result)->toContain(goodNearbyUrl($mathsTeacher))
        ->and($result)->not->toContain(goodNearbyUrl($englishTeacher));
});

test('the qualification_or_skill filter matches by skill when there is no qualification match', function () {
    $skill = CandidateSkill::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Mathematics Tutoring',
    ]);

    $match = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'latitude' => 52.4700,
        'longitude' => -1.9000,
    ]);
    $match->skills()->attach($skill);

    $nonMatch = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'latitude' => 52.4750,
        'longitude' => -1.9050,
    ]);

    $result = (new GoodCandidatesNearby)->handle(new Request([
        'location' => 'Riverside',
        'radius_miles' => 10,
        'qualification_or_skill' => 'Mathematics',
    ]));

    expect($result)->toContain(goodNearbyUrl($match))
        ->and($result)->not->toContain(goodNearbyUrl($nonMatch));
});

test('it reports when the location cannot be found', function () {
    Http::fake([
        'maps.googleapis.com/*' => Http::response(['results' => [], 'status' => 'ZERO_RESULTS']),
    ]);

    $result = (new GoodCandidatesNearby)->handle(new Request(['location' => 'Nowhere In Particular']));

    expect($result)->toBe('Could not find a location matching "Nowhere In Particular".');
});

test('it reports when nothing matches, including the filter term in the message', function () {
    EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'latitude' => 51.5072,
        'longitude' => -0.1276,
    ]);

    $result = (new GoodCandidatesNearby)->handle(new Request([
        'location' => 'Riverside',
        'radius_miles' => 5,
        'qualification_or_skill' => 'maths',
    ]));

    expect($result)->toBe('No candidates found within 5 miles of Riverside School matching "maths".');
});

test('it links candidates to their edit pages and never mentions vacancy matching', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'latitude' => 52.4700,
        'longitude' => -1.9000,
        'average_rating' => 4.5,
        'ratings_count' => 3,
    ]);

    $result = (new GoodCandidatesNearby)->handle(new Request(['location' => 'Riverside', 'radius_miles' => 10]));

    $url = goodNearbyUrl($candidate);

    expect($result)->toContain("[Jane Doe]({$url})")
        ->and($result)->not->toContain('match');
});
