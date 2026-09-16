<?php

use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->company = $this->user->company;
    $this->client = Client::factory()->create(['company_id' => $this->company->id]);
    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id]);
});

function bookingOnDate(EducationCandidate $candidate, Client $client, JobTitle $jobTitle, string $date, array $bookingAttributes = []): Booking
{
    $booking = Booking::factory()->create(array_merge([
        'company_id' => $candidate->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
        // ListBookings' consultant filter defaults to "just the current
        // user's bookings" for an admin — see BookingFilters::consultant()
        // — so a row must belong to the acting admin to show up at all.
        'consultant_id' => auth()->id(),
    ], $bookingAttributes));

    $booking->dayPeriods()->create([
        'company_id' => $candidate->company_id,
        'date' => $date,
        'period' => BookingDayPeriod::FullDay,
    ]);

    return $booking;
}

function bookingForNextWeek(EducationCandidate $candidate, Client $client, JobTitle $jobTitle, array $bookingAttributes = []): Booking
{
    return bookingOnDate($candidate, $client, $jobTitle, now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString(), $bookingAttributes);
}

test('needsRebookForWeek is true for a candidate with nothing booked next week', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    expect($candidate->needsRebookForWeek(now()->addWeek()))->toBeTrue();
});

test('needsRebookForWeek is false once the candidate has a non-cancelled day booked next week', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    bookingForNextWeek($candidate, $this->client, $this->jobTitle);

    expect($candidate->needsRebookForWeek(now()->addWeek()))->toBeFalse();
});

test('needsRebookForWeek ignores a cancelled day', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $booking = bookingForNextWeek($candidate, $this->client, $this->jobTitle);
    $booking->dayPeriods()->update(['cancelled_at' => now()]);

    expect($candidate->needsRebookForWeek(now()->addWeek()))->toBeTrue();
});

test('needsRebookForWeek ignores a merely requested booking', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    bookingForNextWeek($candidate, $this->client, $this->jobTitle, ['status' => BookingStatus::Requested]);

    expect($candidate->needsRebookForWeek(now()->addWeek()))->toBeTrue();
});

test('needsRebookForWeek is candidate-level: a booking with a different client still counts as booked', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $otherClient = Client::factory()->create(['company_id' => $this->company->id]);
    bookingForNextWeek($candidate, $otherClient, $this->jobTitle);

    expect($candidate->needsRebookForWeek(now()->addWeek()))->toBeFalse();
});

test('the Rebook action is visible on a booking row whose candidate has nothing booked next week', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $row = bookingOnDate($candidate, $this->client, $this->jobTitle, now()->toDateString());

    Livewire::test(ListBookings::class)
        ->assertTableActionVisible('rebook', record: $row);
});

test('the Rebook action is hidden on a booking row whose candidate already has next week covered', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $row = bookingOnDate($candidate, $this->client, $this->jobTitle, now()->toDateString());
    bookingForNextWeek($candidate, $this->client, $this->jobTitle);

    Livewire::test(ListBookings::class)
        ->assertTableActionHidden('rebook', record: $row);
});

test('the Rebook action is hidden on a row that is not this week, even when the candidate has nothing booked next week', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $row = bookingOnDate($candidate, $this->client, $this->jobTitle, now()->subMonth()->toDateString());

    Livewire::test(ListBookings::class)
        ->assertTableActionHidden('rebook', record: $row);
});

test('the Rebook action links to a booking-creation form pre-filled with this row\'s candidate, client, job title, and next Monday', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $row = bookingOnDate($candidate, $this->client, $this->jobTitle, now()->toDateString());
    $nextMonday = now()->addWeek()->startOfWeek(Carbon::MONDAY);

    $url = Livewire::test(ListBookings::class)
        ->instance()
        ->getTable()
        ->getAction('rebook')
        ->record($row)
        ->getUrl();

    expect($url)->toBe(BookingResource::getUrl('create', [
        'candidate_id' => $candidate->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'start_date' => $nextMonday->toDateString(),
    ]));
});
