<?php

use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
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
    $this->client = Client::factory()->create(['company_id' => $this->company->id]);
    $this->candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    $this->booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'status' => BookingStatus::Upcoming,
    ]);
});

function addRefreshTestDay(Booking $booking, string $date, array $attributes = [])
{
    return $booking->dayPeriods()->create(array_merge([
        'company_id' => $booking->company_id,
        'date' => $date,
        'period' => BookingDayPeriod::FullDay,
    ], $attributes));
}

test('a booking with no sent days is left untouched', function () {
    addRefreshTestDay($this->booking, now()->toDateString());

    $this->booking->refreshPayrollStatus();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Upcoming);
});

test('a booking with sent but unapproved days is marked awaiting approval', function () {
    addRefreshTestDay($this->booking, now()->toDateString(), ['payroll_confirmation_sent_at' => now()]);

    $this->booking->refreshPayrollStatus();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingApproval);
});

test('a booking with a mix of approved and unapproved sent days is awaiting approval', function () {
    addRefreshTestDay($this->booking, now()->toDateString(), [
        'payroll_confirmation_sent_at' => now(),
        'approved_at' => now(),
    ]);
    addRefreshTestDay($this->booking, now()->addDay()->toDateString(), [
        'payroll_confirmation_sent_at' => now(),
    ]);

    $this->booking->refreshPayrollStatus();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingApproval);
});

test('a booking with every sent day approved is marked approved', function () {
    addRefreshTestDay($this->booking, now()->toDateString(), [
        'payroll_confirmation_sent_at' => now(),
        'approved_at' => now(),
    ]);

    $this->booking->refreshPayrollStatus();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Approved);
});

test('a disputed day marks the booking as awaiting approval, not approved', function () {
    addRefreshTestDay($this->booking, now()->toDateString(), [
        'payroll_confirmation_sent_at' => now(),
        'disputed_at' => now(),
        'dispute_reason' => 'Wrong candidate',
    ]);

    $this->booking->refreshPayrollStatus();

    $fresh = $this->booking->fresh();

    expect($fresh->status)->toBe(BookingStatus::AwaitingApproval)
        ->and($fresh->isDisputed())->toBeTrue()
        ->and($fresh->dispute_reason)->toBe('Wrong candidate');
});

test('a completed booking does not get downgraded by the payroll rollup', function () {
    $this->booking->update(['status' => BookingStatus::Completed]);

    addRefreshTestDay($this->booking, now()->toDateString(), ['payroll_confirmation_sent_at' => now()]);

    $this->booking->refreshPayrollStatus();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Completed);
});
