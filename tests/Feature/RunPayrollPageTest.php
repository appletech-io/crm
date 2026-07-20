<?php

use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Filament\Pages\RunPayroll;
use App\Jobs\SendPayrollConfirmationEmail;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\JobTitle;
use App\Models\User;
use App\Services\Booking\TimesheetPeriod;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
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
    $this->periodStart = TimesheetPeriod::current($this->company)['start'];
});

function createPayrollBooking(User $user, JobTitle $jobTitle, string $date, array $dayAttributes = []): Booking
{
    $client = Client::factory()->create(['company_id' => $user->company_id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $user->company_id]);

    $booking = Booking::factory()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
    ]);

    $booking->dayPeriods()->create(array_merge([
        'company_id' => $user->company_id,
        'date' => $date,
        'period' => BookingDayPeriod::FullDay,
    ], $dayAttributes));

    return $booking;
}

test('a non-admin cannot access the run payroll page', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", 'education');
    Cache::put("user.{$consultant->id}.active_industry_id", 1);

    expect(RunPayroll::canAccess())->toBeFalse();
});

test('an admin can access the run payroll page', function () {
    expect(RunPayroll::canAccess())->toBeTrue();

    Livewire::test(RunPayroll::class)->assertSuccessful();
});

test('the confirm action dispatches one payroll confirmation email per distinct client with bookings this period', function () {
    Queue::fake();

    $bookingA = createPayrollBooking($this->user, $this->jobTitle, $this->periodStart->toDateString());
    $bookingB = createPayrollBooking($this->user, $this->jobTitle, $this->periodStart->copy()->addDay()->toDateString());

    Livewire::test(RunPayroll::class)
        ->callTableAction('confirm')
        ->assertNotified();

    Queue::assertPushed(SendPayrollConfirmationEmail::class, fn ($job) => $job->client->is($bookingA->client)
        && $job->periodStart === $this->periodStart->toDateString());

    Queue::assertPushed(SendPayrollConfirmationEmail::class, fn ($job) => $job->client->is($bookingB->client));

    Queue::assertPushed(SendPayrollConfirmationEmail::class, 2);
});

test('the confirm action does not dispatch for cancelled days or bookings outside the current period', function () {
    Queue::fake();

    createPayrollBooking($this->user, $this->jobTitle, $this->periodStart->toDateString(), ['cancelled_at' => now()]);
    createPayrollBooking($this->user, $this->jobTitle, $this->periodStart->copy()->addMonths(3)->toDateString());

    Livewire::test(RunPayroll::class)->callTableAction('confirm');

    Queue::assertNotPushed(SendPayrollConfirmationEmail::class);
});

test('the confirm button is enabled while a booking in the period is still upcoming', function () {
    createPayrollBooking($this->user, $this->jobTitle, $this->periodStart->toDateString());

    Livewire::test(RunPayroll::class)
        ->assertTableActionEnabled('confirm');
});

test('the confirm button is greyed out once every booking in the period has already been sent', function () {
    $booking = createPayrollBooking($this->user, $this->jobTitle, $this->periodStart->toDateString());
    $booking->update(['status' => BookingStatus::AwaitingApproval]);

    Livewire::test(RunPayroll::class)
        ->assertTableActionDisabled('confirm');
});

test('the confirm button is disabled when there are no bookings in the period at all', function () {
    Livewire::test(RunPayroll::class)
        ->assertTableActionDisabled('confirm');
});

test('the confirm button becomes enabled again once a new upcoming booking is added alongside already-sent ones', function () {
    $sent = createPayrollBooking($this->user, $this->jobTitle, $this->periodStart->toDateString());
    $sent->update(['status' => BookingStatus::AwaitingApproval]);

    createPayrollBooking($this->user, $this->jobTitle, $this->periodStart->copy()->addDay()->toDateString());

    Livewire::test(RunPayroll::class)
        ->assertTableActionEnabled('confirm');
});

test('the table lists this periods non-cancelled bookings and excludes cancelled or out-of-period ones', function () {
    $inPeriod = createPayrollBooking($this->user, $this->jobTitle, $this->periodStart->toDateString());
    $cancelled = createPayrollBooking($this->user, $this->jobTitle, $this->periodStart->toDateString(), ['cancelled_at' => now()]);
    $outOfPeriod = createPayrollBooking($this->user, $this->jobTitle, $this->periodStart->copy()->addMonths(3)->toDateString());

    $inPeriodDay = $inPeriod->dayPeriods()->first();
    $cancelledDay = $cancelled->dayPeriods()->first();
    $outOfPeriodDay = $outOfPeriod->dayPeriods()->first();

    Livewire::test(RunPayroll::class)
        ->assertCanSeeTableRecords([$inPeriodDay])
        ->assertCanNotSeeTableRecords([$cancelledDay, $outOfPeriodDay]);
});

test('navigating to the next and previous period changes which days are visible', function () {
    $currentBooking = createPayrollBooking($this->user, $this->jobTitle, $this->periodStart->toDateString());

    $nextPeriod = TimesheetPeriod::next($this->company, $this->periodStart);
    $nextBooking = createPayrollBooking($this->user, $this->jobTitle, $nextPeriod['start']->toDateString());

    $currentDay = $currentBooking->dayPeriods()->first();
    $nextDay = $nextBooking->dayPeriods()->first();

    $component = Livewire::test(RunPayroll::class)
        ->assertCanSeeTableRecords([$currentDay])
        ->assertCanNotSeeTableRecords([$nextDay]);

    $component->call('goToNextPeriod')
        ->assertCanSeeTableRecords([$nextDay])
        ->assertCanNotSeeTableRecords([$currentDay]);

    $component->call('goToPreviousPeriod')
        ->assertCanSeeTableRecords([$currentDay])
        ->assertCanNotSeeTableRecords([$nextDay]);
});

test('jumping to a period via a valid selectable date moves to that period', function () {
    $futurePeriod = TimesheetPeriod::next($this->company, TimesheetPeriod::next($this->company, $this->periodStart)['start']);
    $futureBooking = createPayrollBooking($this->user, $this->jobTitle, $futurePeriod['start']->toDateString());
    $futureDay = $futureBooking->dayPeriods()->first();

    Livewire::test(RunPayroll::class)
        ->assertCanNotSeeTableRecords([$futureDay])
        ->callTableAction('jumpToPeriod', data: ['date' => $futurePeriod['end']->toDateString()])
        ->assertCanSeeTableRecords([$futureDay]);
});

test('the subheading shows the current period range', function () {
    Livewire::test(RunPayroll::class)
        ->assertSuccessful()
        ->assertSee($this->periodStart->format('jS M Y'));
});
