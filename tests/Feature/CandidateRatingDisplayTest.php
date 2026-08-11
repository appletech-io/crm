<?php

use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Filament\Resources\EducationCandidates\Pages\ListEducationCandidates;
use App\Filament\Resources\HealthcareCandidates\Pages\EditHealthcareCandidate;
use App\Filament\Resources\HealthcareCandidates\Pages\ListHealthcareCandidates;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function actingAsRatingTestUser(string $slug): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    $industry = Industry::factory()->create(['slug' => $slug]);
    Cache::put("user.{$user->id}.active_industry", $industry->slug);
    Cache::put("user.{$user->id}.active_industry_id", $industry->id);

    test()->actingAs($user);

    return $user;
}

test('a candidate with no rated bookings shows not yet rated on the edit page', function () {
    $user = actingAsRatingTestUser('education');
    $candidate = EducationCandidate::factory()->create(['company_id' => $user->company_id]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('Not yet rated');
});

test('the average of only rated bookings is shown on the edit page, unrated bookings are excluded', function () {
    $user = actingAsRatingTestUser('education');
    $client = Client::factory()->create(['company_id' => $user->company_id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $user->company_id]);

    Booking::factory()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_rating' => 4,
        'candidate_rated_at' => now(),
    ]);
    Booking::factory()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_rating' => 5,
        'candidate_rated_at' => now(),
    ]);
    // Not yet rated — must not pull the average down or inflate the count.
    Booking::factory()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_rating' => null,
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('4.5 ★ (2 ratings)');
});

test('a single rating is pluralized correctly on the edit page', function () {
    $user = actingAsRatingTestUser('education');
    $client = Client::factory()->create(['company_id' => $user->company_id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $user->company_id]);

    Booking::factory()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_rating' => 3,
        'candidate_rated_at' => now(),
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('3.0 ★ (1 rating)')
        ->assertDontSee('1 ratings');
});

test('the rating column on the education candidates list shows the average and count', function () {
    $user = actingAsRatingTestUser('education');
    $client = Client::factory()->create(['company_id' => $user->company_id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $user->company_id]);

    Booking::factory()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_rating' => 2,
        'candidate_rated_at' => now(),
    ]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'all')
        ->assertTableColumnStateSet('average_rating', 2.0, record: $candidate)
        ->assertSee('2.0 ★ (1)');
});

test('the healthcare candidates list and edit page also show the average rating', function () {
    $user = actingAsRatingTestUser('healthcare');
    $client = Client::factory()->create(['company_id' => $user->company_id]);
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $user->company_id]);

    Booking::factory()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => HealthcareCandidate::class,
        'candidate_rating' => 5,
        'candidate_rated_at' => now(),
    ]);

    Livewire::test(ListHealthcareCandidates::class)
        ->assertTableColumnStateSet('average_rating', 5.0, record: $candidate);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('5.0 ★ (1 rating)');
});

test('the education candidates rating filter narrows to a minimum star threshold', function () {
    $user = actingAsRatingTestUser('education');
    $client = Client::factory()->create(['company_id' => $user->company_id]);

    $highlyRated = EducationCandidate::factory()->create(['company_id' => $user->company_id]);
    Booking::factory()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'candidate_id' => $highlyRated->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_rating' => 5,
        'candidate_rated_at' => now(),
    ]);

    $poorlyRated = EducationCandidate::factory()->create(['company_id' => $user->company_id]);
    Booking::factory()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'candidate_id' => $poorlyRated->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_rating' => 2,
        'candidate_rated_at' => now(),
    ]);

    $unrated = EducationCandidate::factory()->create(['company_id' => $user->company_id]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'all')
        ->filterTable('average_rating', '4')
        ->assertCanSeeTableRecords([$highlyRated])
        ->assertCanNotSeeTableRecords([$poorlyRated, $unrated]);
});

test('the education candidates rating filter can narrow to only unrated candidates', function () {
    $user = actingAsRatingTestUser('education');
    $client = Client::factory()->create(['company_id' => $user->company_id]);

    $rated = EducationCandidate::factory()->create(['company_id' => $user->company_id]);
    Booking::factory()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'candidate_id' => $rated->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_rating' => 3,
        'candidate_rated_at' => now(),
    ]);

    $unrated = EducationCandidate::factory()->create(['company_id' => $user->company_id]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'all')
        ->filterTable('average_rating', 'unrated')
        ->assertCanSeeTableRecords([$unrated])
        ->assertCanNotSeeTableRecords([$rated]);
});

test('the healthcare candidates rating filter narrows to a minimum star threshold', function () {
    $user = actingAsRatingTestUser('healthcare');
    $client = Client::factory()->create(['company_id' => $user->company_id]);

    $highlyRated = HealthcareCandidate::factory()->create(['company_id' => $user->company_id]);
    Booking::factory()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'candidate_id' => $highlyRated->id,
        'candidate_type' => HealthcareCandidate::class,
        'candidate_rating' => 4,
        'candidate_rated_at' => now(),
    ]);

    $poorlyRated = HealthcareCandidate::factory()->create(['company_id' => $user->company_id]);
    Booking::factory()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'candidate_id' => $poorlyRated->id,
        'candidate_type' => HealthcareCandidate::class,
        'candidate_rating' => 1,
        'candidate_rated_at' => now(),
    ]);

    Livewire::test(ListHealthcareCandidates::class)
        ->filterTable('average_rating', '3')
        ->assertCanSeeTableRecords([$highlyRated])
        ->assertCanNotSeeTableRecords([$poorlyRated]);
});

test('the edit page header title has no rating badge for an unrated candidate', function () {
    $user = actingAsRatingTestUser('education');
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $title = Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->instance()
        ->getTitle();

    expect($title)->toBe('Jane Doe');
});

test('the edit page header title includes a star rating badge for a rated candidate', function () {
    $user = actingAsRatingTestUser('education');
    $client = Client::factory()->create(['company_id' => $user->company_id]);
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    Booking::factory()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_rating' => 4,
        'candidate_rated_at' => now(),
    ]);
    Booking::factory()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_rating' => 5,
        'candidate_rated_at' => now(),
    ]);

    $title = Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->instance()
        ->getTitle();

    expect((string) $title)
        ->toContain('Jane Doe')
        ->toContain('★ 4.5');
});

test('the healthcare edit page header title also includes a star rating badge for a rated candidate', function () {
    $user = actingAsRatingTestUser('healthcare');
    $client = Client::factory()->create(['company_id' => $user->company_id]);
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $user->company_id,
        'first_name' => 'John',
        'last_name' => 'Smith',
    ]);

    Booking::factory()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => HealthcareCandidate::class,
        'candidate_rating' => 2,
        'candidate_rated_at' => now(),
    ]);

    $title = Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->instance()
        ->getTitle();

    expect((string) $title)
        ->toContain('John Smith')
        ->toContain('★ 2.0');
});
