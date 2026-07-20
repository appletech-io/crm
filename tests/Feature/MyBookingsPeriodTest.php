<?php

use App\Enums\BookingDayPeriod;
use App\Enums\TimesheetFrequency;
use App\Filament\Client\Pages\MyBookings;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
use App\Services\Booking\TimesheetPeriod;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($this->industry);

    $this->jobTitle = JobTitle::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $this->client = Client::factory()->create(['company_id' => $this->company->id]);

    $this->contact = ClientContact::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
    ]);

    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'client_contact_id' => $this->contact->id,
    ]);
    $this->user->assignRole('client');

    $this->candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
});

function periodBooking(Company $company, Client $client, EducationCandidate $candidate, JobTitle $jobTitle): Booking
{
    return Booking::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
    ]);
}

function periodSentDay(Booking $booking, string $date)
{
    return $booking->dayPeriods()->create([
        'company_id' => $booking->company_id,
        'date' => $date,
        'period' => BookingDayPeriod::FullDay,
        'payroll_confirmation_sent_at' => now(),
    ]);
}

test('it defaults to showing the clients current period', function () {
    $this->actingAs($this->user);

    $expectedPeriod = TimesheetPeriod::current($this->company);

    $component = Livewire::test(MyBookings::class);

    expect($component->get('periodStart'))->toBe($expectedPeriod['start']->toDateString());
});

test('a day outside the current period is not shown', function () {
    $booking = periodBooking($this->company, $this->client, $this->candidate, $this->jobTitle);

    $inPeriodDay = periodSentDay($booking, now()->toDateString());
    $outOfPeriodDay = periodSentDay($booking, now()->addMonths(2)->toDateString());

    $this->actingAs($this->user);

    Livewire::test(MyBookings::class)
        ->assertCanSeeTableRecords([$inPeriodDay])
        ->assertCanNotSeeTableRecords([$outOfPeriodDay]);
});

test('navigating to the next and previous period changes which days are visible', function () {
    $booking = periodBooking($this->company, $this->client, $this->candidate, $this->jobTitle);

    $currentPeriod = TimesheetPeriod::current($this->company);
    $nextPeriod = TimesheetPeriod::next($this->company, $currentPeriod['start']);

    $currentDay = periodSentDay($booking, $currentPeriod['start']->toDateString());
    $nextDay = periodSentDay($booking, $nextPeriod['start']->toDateString());

    $this->actingAs($this->user);

    $component = Livewire::test(MyBookings::class)
        ->assertCanSeeTableRecords([$currentDay])
        ->assertCanNotSeeTableRecords([$nextDay]);

    $component->call('goToNextPeriod')
        ->assertCanSeeTableRecords([$nextDay])
        ->assertCanNotSeeTableRecords([$currentDay]);

    $component->call('goToPreviousPeriod')
        ->assertCanSeeTableRecords([$currentDay])
        ->assertCanNotSeeTableRecords([$nextDay]);
});

test('going to current period resets navigation back to today', function () {
    $this->actingAs($this->user);

    $component = Livewire::test(MyBookings::class);
    $originalPeriodStart = $component->get('periodStart');

    $component->call('goToNextPeriod')->call('goToNextPeriod');
    expect($component->get('periodStart'))->not->toBe($originalPeriodStart);

    $component->call('goToCurrentPeriod');
    expect($component->get('periodStart'))->toBe($originalPeriodStart);
});

test('jumping to a period via a valid selectable date moves to that period', function () {
    $booking = periodBooking($this->company, $this->client, $this->candidate, $this->jobTitle);

    $currentPeriod = TimesheetPeriod::current($this->company);
    $futurePeriod = TimesheetPeriod::next($this->company, TimesheetPeriod::next($this->company, $currentPeriod['start'])['start']);

    $futureDay = periodSentDay($booking, $futurePeriod['start']->toDateString());

    $this->actingAs($this->user);

    Livewire::test(MyBookings::class)
        ->assertCanNotSeeTableRecords([$futureDay])
        ->callTableAction('jumpToPeriod', data: ['date' => $futurePeriod['end']->toDateString()])
        ->assertCanSeeTableRecords([$futureDay]);
});

test('the subheading shows the current period range', function () {
    $this->actingAs($this->user);

    $period = TimesheetPeriod::current($this->company);

    Livewire::test(MyBookings::class)
        ->assertSuccessful()
        ->assertSee($period['start']->format('jS M Y'))
        ->assertSee($period['end']->format('jS M Y'));
});

test('a monthly frequency client sees days scoped to their monthly period', function () {
    $this->company->update([
        'timesheet_frequency' => TimesheetFrequency::Monthly,
        'timesheet_day_of_month' => 1,
    ]);

    $booking = periodBooking($this->company, $this->client, $this->candidate, $this->jobTitle);

    $period = TimesheetPeriod::current($this->company->fresh());
    $inPeriodDay = periodSentDay($booking, $period['start']->toDateString());

    $nextPeriod = TimesheetPeriod::next($this->company->fresh(), $period['start']);
    $outOfPeriodDay = periodSentDay($booking, $nextPeriod['start']->toDateString());

    $this->actingAs($this->user);

    Livewire::test(MyBookings::class)
        ->assertCanSeeTableRecords([$inPeriodDay])
        ->assertCanNotSeeTableRecords([$outOfPeriodDay]);
});
