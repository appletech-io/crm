<?php

use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\EducationCandidates\Pages\ListEducationCandidates;
use App\Filament\Resources\HealthcareCandidates\Pages\ListHealthcareCandidates;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
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

function bookingForNextWeek(EducationCandidate $candidate, Client $client, JobTitle $jobTitle, array $bookingAttributes = []): Booking
{
    $booking = Booking::factory()->create(array_merge([
        'company_id' => $candidate->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
    ], $bookingAttributes));

    $booking->dayPeriods()->create([
        'company_id' => $candidate->company_id,
        'date' => now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    return $booking;
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

test('the Rebook action is visible for a candidate with nothing booked next week and hidden once booked', function () {
    $unbooked = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $booked = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    bookingForNextWeek($booked, $this->client, $this->jobTitle);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'all')
        ->assertTableActionVisible('rebook', record: $unbooked)
        ->assertTableActionHidden('rebook', record: $booked);
});

test('the Rebook action links to a booking-creation form pre-filled with the candidate and next Monday', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $nextMonday = now()->addWeek()->startOfWeek(Carbon::MONDAY);

    $url = Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'all')
        ->instance()
        ->getTable()
        ->getAction('rebook')
        ->record($candidate)
        ->getUrl();

    expect($url)->toBe(BookingResource::getUrl('create', [
        'candidate_id' => $candidate->id,
        'start_date' => $nextMonday->toDateString(),
    ]));
});

test('the Rebook action is also wired up on the Healthcare candidates table', function () {
    $this->industry->update(['slug' => 'healthcare']);
    Cache::put("user.{$this->user->id}.active_industry", 'healthcare');

    $unbooked = HealthcareCandidate::factory()->create(['company_id' => $this->company->id]);

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'all')
        ->assertTableActionVisible('rebook', record: $unbooked);
});
