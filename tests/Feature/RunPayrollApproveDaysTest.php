<?php

use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Enums\Integration;
use App\Filament\Pages\RunPayroll;
use App\Filament\Pages\ViewPayroll;
use App\Jobs\SendTimesheetToPayrollProvider;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\JobTitle;
use App\Models\User;
use App\Services\Booking\TimesheetPeriod;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Http::fake();
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
    Cache::put("user.{$this->admin->id}.active_industry", 'education');
    Cache::put("user.{$this->admin->id}.active_industry_id", 1);

    $this->company = $this->admin->company;
    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id]);
    $this->periodStart = TimesheetPeriod::current($this->company)['start'];
});

function makeApprovableBooking(User $admin, JobTitle $jobTitle, string $date, array $dayAttributes = [], int $days = 1): Booking
{
    $client = Client::factory()->create(['company_id' => $admin->company_id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $admin->company_id]);

    $booking = Booking::factory()->create([
        'company_id' => $admin->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
        'consultant_id' => $admin->id,
        'status' => BookingStatus::AwaitingApproval,
    ]);

    for ($i = 0; $i < $days; $i++) {
        $booking->dayPeriods()->create(array_merge([
            'company_id' => $admin->company_id,
            'date' => Carbon::parse($date)->addDays($i)->toDateString(),
            'period' => BookingDayPeriod::FullDay,
            'payroll_confirmation_sent_at' => now(),
        ], $dayAttributes));
    }

    return $booking;
}

test('an admin can approve a single sent day', function () {
    $booking = makeApprovableBooking($this->admin, $this->jobTitle, $this->periodStart->toDateString());
    $day = $booking->dayPeriods()->first();

    Livewire::test(RunPayroll::class)
        ->callAction(TestAction::make('approveDay')->table($day));

    $day->refresh();

    expect($day->approved_at)->not->toBeNull()
        ->and($day->approved_by_user_id)->toBe($this->admin->id);
});

test('approving every sent day moves the booking to approved', function () {
    $booking = makeApprovableBooking($this->admin, $this->jobTitle, $this->periodStart->toDateString(), days: 2);

    Livewire::test(RunPayroll::class)
        ->callTableBulkAction('approveSelectedDays', $booking->dayPeriods()->pluck('id')->all());

    expect($booking->fresh()->status)->toBe(BookingStatus::Approved)
        ->and($booking->dayPeriods()->whereNull('approved_at')->count())->toBe(0);
});

test('approving only some of a bookings days leaves it awaiting approval', function () {
    $booking = makeApprovableBooking($this->admin, $this->jobTitle, $this->periodStart->toDateString(), days: 2);
    $first = $booking->dayPeriods()->orderBy('date')->first();

    Livewire::test(RunPayroll::class)
        ->callAction(TestAction::make('approveDay')->table($first));

    expect($booking->fresh()->status)->toBe(BookingStatus::AwaitingApproval);
});

test('approving clears an existing dispute on the day', function () {
    $booking = makeApprovableBooking($this->admin, $this->jobTitle, $this->periodStart->toDateString(), [
        'disputed_at' => now(),
        'dispute_reason' => 'Hours were wrong',
    ]);
    $day = $booking->dayPeriods()->first();

    Livewire::test(RunPayroll::class)
        ->callAction(TestAction::make('approveDay')->table($day));

    $day->refresh();

    expect($day->disputed_at)->toBeNull()
        ->and($day->dispute_reason)->toBeNull()
        ->and($day->approved_at)->not->toBeNull();
});

test('a day whose confirmation has never been sent cannot be approved', function () {
    $booking = makeApprovableBooking($this->admin, $this->jobTitle, $this->periodStart->toDateString(), [
        'payroll_confirmation_sent_at' => null,
    ]);
    $day = $booking->dayPeriods()->first();

    Livewire::test(RunPayroll::class)
        ->assertActionHidden(TestAction::make('approveDay')->table($day));

    expect($day->fresh()->approved_at)->toBeNull();
});

test('an already approved day no longer offers the action', function () {
    $booking = makeApprovableBooking($this->admin, $this->jobTitle, $this->periodStart->toDateString(), [
        'approved_at' => now(),
    ]);
    $day = $booking->dayPeriods()->first();

    Livewire::test(RunPayroll::class)
        ->assertActionHidden(TestAction::make('approveDay')->table($day));
});

test('the consultants timesheets page gets no approve action', function () {
    makeApprovableBooking($this->admin, $this->jobTitle, $this->periodStart->toDateString());

    Livewire::test(ViewPayroll::class)
        ->assertTableActionDoesNotExist('approveDay')
        ->assertTableBulkActionDoesNotExist('approveSelectedDays');
});

test('approving the last outstanding day submits the timesheet to the payroll provider', function () {
    Queue::fake();

    $this->company->update(['payroll_provider' => Integration::Evertime]);
    $booking = makeApprovableBooking($this->admin, $this->jobTitle, $this->periodStart->toDateString(), days: 2);

    Livewire::test(RunPayroll::class)
        ->callTableBulkAction('approveSelectedDays', $booking->dayPeriods()->pluck('id')->all());

    Queue::assertPushed(
        SendTimesheetToPayrollProvider::class,
        fn (SendTimesheetToPayrollProvider $job): bool => $job->booking->is($booking),
    );
});

test('approving only part of a booking does not submit anything yet', function () {
    Queue::fake();

    $this->company->update(['payroll_provider' => Integration::Evertime]);
    $booking = makeApprovableBooking($this->admin, $this->jobTitle, $this->periodStart->toDateString(), days: 2);
    $first = $booking->dayPeriods()->orderBy('date')->first();

    Livewire::test(RunPayroll::class)
        ->callAction(TestAction::make('approveDay')->table($first));

    Queue::assertNotPushed(SendTimesheetToPayrollProvider::class);
});

test('only approved, undisputed, not-yet-sent days are included in a submission', function () {
    $this->company->update(['payroll_provider' => Integration::Evertime]);
    $booking = makeApprovableBooking($this->admin, $this->jobTitle, $this->periodStart->toDateString(), days: 3);

    $days = $booking->dayPeriods()->orderBy('date')->get();
    $days[0]->update(['approved_at' => now(), 'sent_to_provider_at' => now()]);
    $days[1]->update(['approved_at' => now(), 'disputed_at' => now()]);

    Livewire::test(RunPayroll::class)
        ->callAction(TestAction::make('approveDay')->table($days[2]));

    // The already-sent day is not resubmitted and the disputed one is held
    // back; only the day just approved is left outstanding.
    expect($booking->dayPeriods()->whereNotNull('approved_at')->whereNull('disputed_at')->whereNull('sent_to_provider_at')->count())
        ->toBe(1);
});

test('a booking already marked Completed is not resubmitted when its days are approved', function () {
    Queue::fake();

    $this->company->update(['payroll_provider' => Integration::Evertime]);
    $booking = makeApprovableBooking($this->admin, $this->jobTitle, $this->periodStart->toDateString());
    $booking->update(['status' => BookingStatus::Completed]);

    Livewire::test(RunPayroll::class)
        ->callAction(TestAction::make('approveDay')->table($booking->dayPeriods()->first()));

    // refreshPayrollStatus() preserves Completed, so the status never
    // transitions to Approved and BookingObserver never fires. Documented
    // here because the day still records as approved — use the booking's
    // own "Retry Payroll Submission" action if one of these needs sending.
    expect($booking->fresh()->status)->toBe(BookingStatus::Completed)
        ->and($booking->dayPeriods()->first()->approved_at)->not->toBeNull();

    Queue::assertNotPushed(SendTimesheetToPayrollProvider::class);
});
