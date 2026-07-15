<?php

use App\Enums\BookingDayPeriod;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Widgets\WeeklyBookingsByClient;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
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
    Cache::put("user.{$this->user->id}.active_industry", 'education');
    Cache::put("user.{$this->user->id}.active_industry_id", 1);

    $this->company = $this->user->company;

    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id]);
    $this->client = Client::factory()->create(['company_id' => $this->company->id, 'name' => 'Ashlawn School']);
    $this->candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Stephen',
        'last_name' => 'Platts',
    ]);
});

function bookingWithDayPeriod(Booking $booking, string $date, BookingDayPeriod $period = BookingDayPeriod::FullDay): void
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

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    bookingWithDayPeriod($booking, $monday->toDateString());
    bookingWithDayPeriod($booking, $monday->copy()->addDay()->toDateString());

    Livewire::test(WeeklyBookingsByClient::class)
        ->assertCanSeeTableRecords([$booking])
        ->assertTableColumnStateSet('candidate_name', 'Stephen Platts', $booking)
        ->assertTableColumnStateSet('day_0', 'booked', $booking)
        ->assertTableColumnStateSet('day_1', 'booked', $booking)
        ->assertTableColumnStateSet('day_2', 'empty', $booking);
});

test('a cancelled day shows as cancelled rather than booked', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => $monday->toDateString(),
        'period' => BookingDayPeriod::FullDay,
        'cancelled_at' => now(),
    ]);
    bookingWithDayPeriod($booking, $monday->copy()->addDay()->toDateString());

    Livewire::test(WeeklyBookingsByClient::class)
        ->assertTableColumnStateSet('day_0', 'cancelled', $booking)
        ->assertTableColumnStateSet('day_1', 'booked', $booking);
});

test('each row links through to the booking edit page', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($booking, $monday->toDateString());

    $html = Livewire::test(WeeklyBookingsByClient::class)->html();

    expect($html)->toContain(
        BookingResource::getUrl('edit', ['record' => $booking])
    );
});

test('the weekly table can be filtered by client and by candidate', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $otherClient = Client::factory()->create(['company_id' => $this->company->id]);
    $otherCandidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    $matchingBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($matchingBooking, $monday->toDateString());

    $otherClientBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $otherClient->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($otherClientBooking, $monday->toDateString());

    $otherCandidateBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $otherCandidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($otherCandidateBooking, $monday->toDateString());

    Livewire::test(WeeklyBookingsByClient::class)
        ->filterTable('client_id', $this->client->id)
        ->assertCanSeeTableRecords([$matchingBooking, $otherCandidateBooking])
        ->assertCanNotSeeTableRecords([$otherClientBooking])
        ->removeTableFilter('client_id')
        ->filterTable('candidate_id', $this->candidate->id)
        ->assertCanSeeTableRecords([$matchingBooking, $otherClientBooking])
        ->assertCanNotSeeTableRecords([$otherCandidateBooking]);
});

test('it excludes bookings outside the selected week', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $thisWeekBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($thisWeekBooking, $monday->toDateString());

    $nextWeekBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
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

    $thisWeekBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($thisWeekBooking, $monday->toDateString());

    $nextWeekBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($nextWeekBooking, $monday->copy()->addWeek()->toDateString());

    $previousWeekBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
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

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($booking, $nextMonday->toDateString());
    bookingWithDayPeriod($booking, $nextMonday->copy()->addDays(2)->toDateString());

    Livewire::test(WeeklyBookingsByClient::class)
        ->call('nextWeek')
        ->assertTableColumnStateSet('day_0', 'booked', $booking)
        ->assertTableColumnStateSet('day_1', 'empty', $booking)
        ->assertTableColumnStateSet('day_2', 'booked', $booking)
        ->assertTableColumnStateSet('day_3', 'empty', $booking);
});

test('it labels soft-deleted clients and candidates', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($booking, $monday->toDateString());

    $this->client->delete();
    $this->candidate->delete();

    Livewire::test(WeeklyBookingsByClient::class)
        ->assertTableColumnStateSet('candidate_name', 'Stephen Platts (deleted)', $booking);
});

test('the bookings list page renders successfully with the weekly widget', function () {
    Livewire::test(ListBookings::class)
        ->assertSuccessful();
});

test('an unbooked day icon with no booking the day before links through to the create page prefilled with candidate, client, job title and that date', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($booking, $monday->toDateString());

    $html = Livewire::test(WeeklyBookingsByClient::class)->html();

    // Thursday (offset 3): Wednesday (offset 2) is unbooked, so this should still create.
    $quickAddUrl = BookingResource::getUrl('create', [
        'candidate_id' => $this->candidate->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'start_date' => $monday->copy()->addDays(3)->toDateString(),
    ]);

    expect($html)->toContain(e($quickAddUrl));
});

test('an unbooked day icon immediately after a booked day links through to the edit page for that booking instead of create', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($booking, $monday->toDateString());

    $html = Livewire::test(WeeklyBookingsByClient::class)->html();

    $editUrl = BookingResource::getUrl('edit', ['record' => $booking]);
    $createUrlForTuesday = BookingResource::getUrl('create', [
        'candidate_id' => $this->candidate->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'start_date' => $monday->copy()->addDay()->toDateString(),
    ]);

    expect($html)->toContain(e($editUrl))
        ->and($html)->not->toContain(e($createUrlForTuesday));
});

test('mondays unbooked icon always links to create since there is no visible previous day in the row', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $thisWeekBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($thisWeekBooking, $monday->copy()->addDay()->toDateString());

    $html = Livewire::test(WeeklyBookingsByClient::class)->html();

    $createUrlForMonday = BookingResource::getUrl('create', [
        'candidate_id' => $this->candidate->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'start_date' => $monday->toDateString(),
    ]);

    expect($html)->toContain(e($createUrlForMonday));
});

test('a booked day icon does not have a quick-add link', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithDayPeriod($booking, $monday->toDateString());

    $html = Livewire::test(WeeklyBookingsByClient::class)->html();

    $bookedDayUrl = BookingResource::getUrl('create', [
        'candidate_id' => $this->candidate->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'start_date' => $monday->toDateString(),
    ]);

    expect($html)->not->toContain(e($bookedDayUrl));
});

test('the list page defaults to the weekly section and can switch to the all section showing the standard bookings table', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    Livewire::test(ListBookings::class)
        ->assertSet('activeSection', 'weekly')
        ->assertSuccessful()
        ->set('activeSection', 'all')
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$booking]);
});
