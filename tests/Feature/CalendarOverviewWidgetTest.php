<?php

use App\Enums\BookingDayPeriod;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Widgets\CalendarOverviewWidget;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Support\Facades\Cache;

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

function fetchCalendarEvents(string $start, string $end)
{
    $fetchInfo = new FetchInfo(['startStr' => $start, 'endStr' => $end]);

    $widget = new CalendarOverviewWidget;
    $method = new ReflectionMethod($widget, 'getEvents');
    $method->setAccessible(true);

    return $method->invoke($widget, $fetchInfo);
}

function bookingWithCalendarDay(Booking $booking, string $date, array $attributes = []): void
{
    $booking->dayPeriods()->create(array_merge([
        'company_id' => $booking->company_id,
        'date' => $date,
        'period' => BookingDayPeriod::FullDay,
    ], $attributes));
}

test('it returns an event for a booking day within the fetched range', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithCalendarDay($booking, '2026-08-10');

    $events = fetchCalendarEvents('2026-08-01', '2026-08-31');

    expect($events)->toHaveCount(1);

    $event = $events->first();

    expect($event->getTitle())->toContain('Stephen Platts')
        ->and($event->getTitle())->toContain('Ashlawn School')
        ->and($event->getExtendedProps()['url'])->toBe(BookingResource::getUrl('edit', ['record' => $booking]));
});

test('it excludes booking days outside the fetched range', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithCalendarDay($booking, '2026-09-15');

    $events = fetchCalendarEvents('2026-08-01', '2026-08-31');

    expect($events)->toHaveCount(0);
});

test('a cancelled day is coloured differently from a normal day', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithCalendarDay($booking, '2026-08-10', ['cancelled_at' => now()]);

    $events = fetchCalendarEvents('2026-08-01', '2026-08-31');

    expect($events->first()->getBackgroundColor())->toBe('#9ca3af');
});

test('a disputed day is coloured as danger', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    bookingWithCalendarDay($booking, '2026-08-10', ['payroll_confirmation_sent_at' => now(), 'disputed_at' => now(), 'dispute_reason' => 'test']);

    $events = fetchCalendarEvents('2026-08-01', '2026-08-31');

    expect($events->first()->getBackgroundColor())->toBe('#ef4444');
});

test('a consultant only sees their own bookings on the calendar', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $ownBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'consultant_id' => $consultant->id,
    ]);
    bookingWithCalendarDay($ownBooking, '2026-08-10');

    $otherBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'consultant_id' => $this->user->id,
    ]);
    bookingWithCalendarDay($otherBooking, '2026-08-11');

    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", 'education');
    Cache::put("user.{$consultant->id}.active_industry_id", 1);

    $events = fetchCalendarEvents('2026-08-01', '2026-08-31');

    expect($events)->toHaveCount(1);
});
