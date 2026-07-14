<?php

use App\Enums\BookingDayPeriod;
use App\Filament\Resources\EducationBookings\Pages\ListEducationBookings;
use App\Filament\Resources\EducationBookings\Widgets\WeeklyBookingsByClient;
use App\Models\EducationBooking;
use App\Models\EducationCandidate;
use App\Models\EducationClient;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
    Cache::put("user.{$this->user->id}.active_industry_id", 1);

    $this->company = $this->user->company;

    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id]);
    $this->client = EducationClient::factory()->create(['company_id' => $this->company->id, 'name' => 'Ashlawn School']);
    $this->candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Stephen',
        'last_name' => 'Platts',
    ]);
});

function bookingWithDayPeriod(EducationBooking $booking, string $date, BookingDayPeriod $period = BookingDayPeriod::FullDay): void
{
    $booking->dayPeriods()->create([
        'company_id' => $booking->company_id,
        'date' => $date,
        'period' => $period,
    ]);
}

test('the widget defaults to the current monday-to-sunday week', function () {
    Livewire::test(WeeklyBookingsByClient::class)
        ->assertSuccessful()
        ->assertSet('weekStart', now()->startOfWeek(Carbon::MONDAY)->toDateString());
});

test('it shows bookings for the current week, grouped by client', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $booking = EducationBooking::factory()->create([
        'company_id' => $this->company->id,
        'education_client_id' => $this->client->id,
        'education_candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    bookingWithDayPeriod($booking, $monday->toDateString());
    bookingWithDayPeriod($booking, $monday->copy()->addDay()->toDateString());

    Livewire::test(WeeklyBookingsByClient::class)
        ->assertCanSeeTableRecords([$booking])
        ->assertTableColumnStateSet('candidate_name', 'Stephen Platts', $booking)
        ->assertTableColumnStateSet('day_0', true, $booking)
        ->assertTableColumnStateSet('day_1', true, $booking)
        ->assertTableColumnStateSet('day_2', false, $booking);
});

test('the weekly table can be filtered by client and by candidate', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $otherClient = EducationClient::factory()->create(['company_id' => $this->company->id]);
    $otherCandidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    $matchingBooking = EducationBooking::factory()->create([
        'company_id' => $this->company->id,
        'education_client_id' => $this->client->id,
        'education_candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($matchingBooking, $monday->toDateString());

    $otherClientBooking = EducationBooking::factory()->create([
        'company_id' => $this->company->id,
        'education_client_id' => $otherClient->id,
        'education_candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($otherClientBooking, $monday->toDateString());

    $otherCandidateBooking = EducationBooking::factory()->create([
        'company_id' => $this->company->id,
        'education_client_id' => $this->client->id,
        'education_candidate_id' => $otherCandidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($otherCandidateBooking, $monday->toDateString());

    Livewire::test(WeeklyBookingsByClient::class)
        ->filterTable('education_client_id', $this->client->id)
        ->assertCanSeeTableRecords([$matchingBooking, $otherCandidateBooking])
        ->assertCanNotSeeTableRecords([$otherClientBooking])
        ->removeTableFilter('education_client_id')
        ->filterTable('education_candidate_id', $this->candidate->id)
        ->assertCanSeeTableRecords([$matchingBooking, $otherClientBooking])
        ->assertCanNotSeeTableRecords([$otherCandidateBooking]);
});

test('it excludes bookings outside the selected week', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $thisWeekBooking = EducationBooking::factory()->create([
        'company_id' => $this->company->id,
        'education_client_id' => $this->client->id,
        'education_candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($thisWeekBooking, $monday->toDateString());

    $nextWeekBooking = EducationBooking::factory()->create([
        'company_id' => $this->company->id,
        'education_client_id' => $this->client->id,
        'education_candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($nextWeekBooking, $monday->copy()->addWeek()->toDateString());

    Livewire::test(WeeklyBookingsByClient::class)
        ->assertCanSeeTableRecords([$thisWeekBooking])
        ->assertCanNotSeeTableRecords([$nextWeekBooking])
        ->assertCountTableRecords(1);
});

test('previous week, next week, and current week navigation actually change which bookings the table shows', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $thisWeekBooking = EducationBooking::factory()->create([
        'company_id' => $this->company->id,
        'education_client_id' => $this->client->id,
        'education_candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($thisWeekBooking, $monday->toDateString());

    $nextWeekBooking = EducationBooking::factory()->create([
        'company_id' => $this->company->id,
        'education_client_id' => $this->client->id,
        'education_candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($nextWeekBooking, $monday->copy()->addWeek()->toDateString());

    $previousWeekBooking = EducationBooking::factory()->create([
        'company_id' => $this->company->id,
        'education_client_id' => $this->client->id,
        'education_candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($previousWeekBooking, $monday->copy()->subWeek()->toDateString());

    Livewire::test(WeeklyBookingsByClient::class)
        ->assertCanSeeTableRecords([$thisWeekBooking])
        ->assertCountTableRecords(1)
        ->call('nextWeek')
        ->assertSet('weekStart', $monday->copy()->addWeek()->toDateString())
        ->assertCanSeeTableRecords([$nextWeekBooking])
        ->assertCountTableRecords(1)
        ->call('previousWeek')
        ->call('previousWeek')
        ->assertSet('weekStart', $monday->copy()->subWeek()->toDateString())
        ->assertCanSeeTableRecords([$previousWeekBooking])
        ->assertCountTableRecords(1)
        ->call('goToCurrentWeek')
        ->assertSet('weekStart', $monday->toDateString())
        ->assertCanSeeTableRecords([$thisWeekBooking])
        ->assertCountTableRecords(1);
});

test('the day columns show the correct booked days after switching weeks, not the previous weeks days', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $nextMonday = $monday->copy()->addWeek();

    $booking = EducationBooking::factory()->create([
        'company_id' => $this->company->id,
        'education_client_id' => $this->client->id,
        'education_candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($booking, $nextMonday->toDateString());
    bookingWithDayPeriod($booking, $nextMonday->copy()->addDays(2)->toDateString());

    Livewire::test(WeeklyBookingsByClient::class)
        ->call('nextWeek')
        ->assertTableColumnStateSet('day_0', true, $booking)
        ->assertTableColumnStateSet('day_1', false, $booking)
        ->assertTableColumnStateSet('day_2', true, $booking)
        ->assertTableColumnStateSet('day_3', false, $booking);
});

test('it labels soft-deleted clients and candidates', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $booking = EducationBooking::factory()->create([
        'company_id' => $this->company->id,
        'education_client_id' => $this->client->id,
        'education_candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($booking, $monday->toDateString());

    $this->client->delete();
    $this->candidate->delete();

    Livewire::test(WeeklyBookingsByClient::class)
        ->assertTableColumnStateSet('candidate_name', 'Stephen Platts (deleted)', $booking);
});

test('the bookings list page renders successfully with the weekly widget', function () {
    Livewire::test(ListEducationBookings::class)
        ->assertSuccessful();
});

test('the list page defaults to the weekly section and can switch to the all section showing the standard bookings table', function () {
    $booking = EducationBooking::factory()->create([
        'company_id' => $this->company->id,
        'education_client_id' => $this->client->id,
        'education_candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    Livewire::test(ListEducationBookings::class)
        ->assertSet('activeSection', 'weekly')
        ->assertSuccessful()
        ->set('activeSection', 'all')
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$booking]);
});
