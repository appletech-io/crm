<?php

use App\Ai\Tools\SearchBookings;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->client = Client::factory()->create(['company_id' => $this->user->company_id]);
    $this->jobTitle = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);
    $this->candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);
});

test('it returns bookings matching the client name filter', function () {
    Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $result = (new SearchBookings)->handle(new Request(['client_name' => $this->client->name]));

    expect($result)->toContain($this->client->name)
        ->and($result)->toContain('Jane Doe')
        ->and($result)->toContain($this->jobTitle->name);
});

test('it matches a candidate by their full first and last name together', function () {
    Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $result = (new SearchBookings)->handle(new Request(['candidate_name' => 'Jane Doe']));

    expect($result)->toContain('Jane Doe');
});

test('it links the candidate, client, and booking to their edit pages', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $result = (new SearchBookings)->handle(new Request(['client_name' => $this->client->name]));

    $candidateUrl = EducationCandidateResource::getUrl('edit', ['record' => $this->candidate]);
    $clientUrl = ClientResource::getUrl('edit', ['record' => $this->client]);
    $bookingUrl = BookingResource::getUrl('edit', ['record' => $booking]);

    expect($result)->toContain("[Jane Doe]({$candidateUrl})")
        ->and($result)->toContain("[{$this->client->name}]({$clientUrl})")
        ->and($result)->toContain("[Booking #{$booking->id}]({$bookingUrl})");
});

test('it paginates results and reports how many more match', function () {
    Booking::factory()->count(51)->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $firstPage = (new SearchBookings)->handle(new Request(['client_name' => $this->client->name]));

    expect($firstPage)->toContain('Showing 50 of 51 — 1 more match. Ask to see the next 50 to continue.');

    $secondPage = (new SearchBookings)->handle(new Request(['client_name' => $this->client->name, 'offset' => 50]));

    expect($secondPage)->not->toContain('more match');
});

test('it returns a plain message when nothing matches', function () {
    $result = (new SearchBookings)->handle(new Request(['client_name' => 'Nonexistent Client']));

    expect($result)->toBe('No bookings matched.');
});

test('it does not return bookings belonging to a different company', function () {
    $otherClient = Client::factory()->create();
    $otherCandidate = EducationCandidate::factory()->create();

    Booking::factory()->create([
        'company_id' => $otherClient->company_id,
        'client_id' => $otherClient->id,
        'candidate_id' => $otherCandidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);

    $result = (new SearchBookings)->handle(new Request(['client_name' => $otherClient->name]));

    expect($result)->toBe('No bookings matched.');
});

test('it filters by region matched against the client address', function () {
    $leicesterClient = Client::factory()->create(['company_id' => $this->user->company_id, 'city' => 'Leicester']);
    $manchesterClient = Client::factory()->create(['company_id' => $this->user->company_id, 'city' => 'Manchester']);

    Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $leicesterClient->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $manchesterClient->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $result = (new SearchBookings)->handle(new Request(['region' => 'Leicester']));

    expect($result)->toContain($leicesterClient->name)
        ->and($result)->not->toContain($manchesterClient->name);
});

test('it filters by status', function () {
    Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'status' => 'completed',
    ]);

    $matching = (new SearchBookings)->handle(new Request(['status' => 'completed']));
    $nonMatching = (new SearchBookings)->handle(new Request(['status' => 'upcoming']));

    expect($matching)->toContain('Completed');
    expect($nonMatching)->toBe('No bookings matched.');
});
