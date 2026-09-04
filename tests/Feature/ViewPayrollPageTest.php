<?php

use App\Enums\BookingDayPeriod;
use App\Filament\Pages\ViewPayroll;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\JobTitle;
use App\Models\User;
use App\Services\Booking\TimesheetPeriod;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = User::factory()->create()->company;

    $this->consultant = User::factory()->create(['company_id' => $this->company->id]);
    $this->consultant->assignRole('consultant');
    $this->actingAs($this->consultant);
    Cache::put("user.{$this->consultant->id}.active_industry", 'education');
    Cache::put("user.{$this->consultant->id}.active_industry_id", 1);

    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id]);
    $this->periodStart = TimesheetPeriod::current($this->company)['start'];
});

function createConsultantPayrollBooking(User $consultant, JobTitle $jobTitle, string $date, array $dayAttributes = []): Booking
{
    $client = Client::factory()->create(['company_id' => $consultant->company_id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $consultant->company_id]);

    $booking = Booking::factory()->create([
        'company_id' => $consultant->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
        'consultant_id' => $consultant->id,
    ]);

    $booking->dayPeriods()->create(array_merge([
        'company_id' => $consultant->company_id,
        'date' => $date,
        'period' => BookingDayPeriod::FullDay,
    ], $dayAttributes));

    return $booking;
}

test('an admin can access the view payroll page', function () {
    $admin = User::factory()->create(['company_id' => $this->company->id]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    expect(ViewPayroll::canAccess())->toBeTrue();

    Livewire::test(ViewPayroll::class)->assertSuccessful();
});

test('a client can access the view payroll page', function () {
    $client = User::factory()->create(['company_id' => $this->company->id]);
    $client->assignRole('client');
    $this->actingAs($client);

    expect(ViewPayroll::canAccess())->toBeTrue();
});

test('a consultant can access the view payroll page', function () {
    expect(ViewPayroll::canAccess())->toBeTrue();

    Livewire::test(ViewPayroll::class)->assertSuccessful();
});

test('a consultant only sees their own bookings, not another consultants', function () {
    $own = createConsultantPayrollBooking($this->consultant, $this->jobTitle, $this->periodStart->toDateString());

    $otherConsultant = User::factory()->create(['company_id' => $this->company->id]);
    $otherConsultant->assignRole('consultant');
    $other = createConsultantPayrollBooking($otherConsultant, $this->jobTitle, $this->periodStart->toDateString());

    Livewire::test(ViewPayroll::class)
        ->assertCanSeeTableRecords([$own->dayPeriods()->first()])
        ->assertCanNotSeeTableRecords([$other->dayPeriods()->first()]);
});

test('an admin viewing the page only sees their own bookings, not every booking at the company', function () {
    $admin = User::factory()->create(['company_id' => $this->company->id]);
    $admin->assignRole('admin');
    $this->actingAs($admin);
    Cache::put("user.{$admin->id}.active_industry", 'education');
    Cache::put("user.{$admin->id}.active_industry_id", 1);

    $own = createConsultantPayrollBooking($admin, $this->jobTitle, $this->periodStart->toDateString());
    $someoneElses = createConsultantPayrollBooking($this->consultant, $this->jobTitle, $this->periodStart->toDateString());

    Livewire::test(ViewPayroll::class)
        ->assertCanSeeTableRecords([$own->dayPeriods()->first()])
        ->assertCanNotSeeTableRecords([$someoneElses->dayPeriods()->first()]);
});

test('there is no confirm or export action available — the page is read only', function () {
    createConsultantPayrollBooking($this->consultant, $this->jobTitle, $this->periodStart->toDateString());

    Livewire::test(ViewPayroll::class)
        ->assertActionDoesNotExist('confirm')
        ->assertActionDoesNotExist('exportPayrollCsv');
});

test('navigating to the next and previous period changes which days are visible', function () {
    $currentBooking = createConsultantPayrollBooking($this->consultant, $this->jobTitle, $this->periodStart->toDateString());

    $nextPeriod = TimesheetPeriod::next($this->company, $this->periodStart);
    $nextBooking = createConsultantPayrollBooking($this->consultant, $this->jobTitle, $nextPeriod['start']->toDateString());

    $currentDay = $currentBooking->dayPeriods()->first();
    $nextDay = $nextBooking->dayPeriods()->first();

    $component = Livewire::test(ViewPayroll::class)
        ->assertCanSeeTableRecords([$currentDay])
        ->assertCanNotSeeTableRecords([$nextDay]);

    $component->call('goToNextPeriod')
        ->assertCanSeeTableRecords([$nextDay])
        ->assertCanNotSeeTableRecords([$currentDay]);
});

test('the subheading shows the current period range', function () {
    Livewire::test(ViewPayroll::class)
        ->assertSuccessful()
        ->assertSee($this->periodStart->format('jS M Y'));
});

test('the payroll status reflects whether the client has approved or disputed', function () {
    $approved = createConsultantPayrollBooking($this->consultant, $this->jobTitle, $this->periodStart->toDateString(), [
        'payroll_confirmation_sent_at' => now(),
        'approved_at' => now(),
    ]);

    Livewire::test(ViewPayroll::class)
        ->assertSee('Approved');
});
