<?php

use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
use App\Services\Clients\ClientStatusColour;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

function statusColourBooking(Client $client, array $bookingAttributes = [], array $dayAttributes = []): Booking
{
    $company = Company::find($client->company_id);
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);
    $jobTitle = JobTitle::factory()->create(['company_id' => $company->id]);

    $booking = Booking::factory()->create(array_merge([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
    ], $bookingAttributes));

    $booking->dayPeriods()->create(array_merge([
        'company_id' => $company->id,
        'date' => now()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ], $dayAttributes));

    return $booking;
}

test('a client with a booked day today is Green', function () {
    $client = Client::factory()->create();
    statusColourBooking($client);

    expect(ClientStatusColour::for($client))->toBe('success');
});

test('a cancelled day today does not count towards Green', function () {
    $client = Client::factory()->create(['created_at' => now()]);
    statusColourBooking(
        $client,
        ['start_date' => now()->toDateString(), 'end_date' => now()->toDateString()],
        ['cancelled_at' => now()],
    );

    expect(ClientStatusColour::for($client))->toBeNull();
});

test('a merely requested booking today does not count towards Green', function () {
    $client = Client::factory()->create(['created_at' => now()]);
    statusColourBooking($client, ['status' => BookingStatus::Requested]);

    expect(ClientStatusColour::for($client))->toBeNull();
});

test('a client with booking history quiet for 60+ days is Yellow', function () {
    $client = Client::factory()->create();
    statusColourBooking($client, ['end_date' => now()->subDays(61)->toDateString()], ['date' => now()->subDays(61)->toDateString()]);

    expect(ClientStatusColour::for($client))->toBe('yellow');
});

test('a client with booking history quiet for only 30 days is left uncoloured', function () {
    $client = Client::factory()->create();
    statusColourBooking($client, ['end_date' => now()->subDays(30)->toDateString()], ['date' => now()->subDays(30)->toDateString()]);

    expect(ClientStatusColour::for($client))->toBeNull();
});

test('a client who has never booked and has been a client for 14+ days is Orange', function () {
    $client = Client::factory()->create(['created_at' => now()->subDays(20)]);

    expect(ClientStatusColour::for($client))->toBe('orange');
});

test('a client who has never booked but was only just added is left uncoloured', function () {
    $client = Client::factory()->create(['created_at' => now()->subDays(5)]);

    expect(ClientStatusColour::for($client))->toBeNull();
});

test('a client with only a requested (never-accepted) booking is still treated as never booked', function () {
    $client = Client::factory()->create(['created_at' => now()->subDays(20)]);
    statusColourBooking($client, ['status' => BookingStatus::Requested, 'end_date' => now()->subDays(90)->toDateString()], ['date' => now()->subDays(90)->toDateString()]);

    expect(ClientStatusColour::for($client))->toBe('orange');
});

test('Green takes precedence over a lapsed booking history', function () {
    $client = Client::factory()->create();
    statusColourBooking($client, ['end_date' => now()->subDays(90)->toDateString()], ['date' => now()->subDays(90)->toDateString()]);
    statusColourBooking($client);

    expect(ClientStatusColour::for($client))->toBe('success');
});

test('the clients list renders successfully with clients in every colour state', function () {
    $this->seed(RoleSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    $industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$user->id}.active_industry", 'education');
    Cache::put("user.{$user->id}.active_industry_id", $industry->id);

    $green = Client::factory()->create(['company_id' => $user->company_id, 'industry_id' => $industry->id]);
    statusColourBooking($green);

    Client::factory()->create([
        'company_id' => $user->company_id,
        'industry_id' => $industry->id,
        'created_at' => now()->subDays(20),
    ]);

    $yellow = Client::factory()->create(['company_id' => $user->company_id, 'industry_id' => $industry->id]);
    statusColourBooking($yellow, ['end_date' => now()->subDays(61)->toDateString()], ['date' => now()->subDays(61)->toDateString()]);

    Livewire::test(ListClients::class)->assertSuccessful();
});
