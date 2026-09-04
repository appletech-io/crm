<?php

use App\Enums\BookingDayPeriod;
use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Filament\Widgets\BookingTimesheetOverview;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
use App\Services\Booking\TimesheetPeriod;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->company = $this->user->company;
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->client = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $this->candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id]);
});

function sentDay(Booking $booking, string $date, BookingDayPeriod $period = BookingDayPeriod::FullDay, array $overrides = []): BookingDay
{
    return $booking->dayPeriods()->create(array_merge([
        'company_id' => $booking->company_id,
        'date' => $date,
        'period' => $period,
        'approved_at' => now(),
        'sent_to_provider_at' => now(),
    ], $overrides));
}

test('periodRows returns an empty array when the booking has no sent days', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    expect(BookingTimesheetOverview::periodRows($booking))->toBe([]);
});

test('it ignores days that are not both approved and sent', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
        'approved_at' => now(),
        'sent_to_provider_at' => null,
    ]);

    expect(BookingTimesheetOverview::periodRows($booking))->toBe([]);
});

test('it groups sent days within the same billing period into one row with the total margin', function () {
    $monday = TimesheetPeriod::current($this->company)['start'];

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    sentDay($booking, $monday->toDateString());
    sentDay($booking, $monday->copy()->addDay()->toDateString());

    $rows = BookingTimesheetOverview::periodRows($booking);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['days_count'])->toBe(2)
        ->and($rows[0]['total_margin_label'])->toBe('£100.00')
        ->and($rows[0]['days'])->toHaveCount(2);
});

test('it splits days into separate rows across different billing periods', function () {
    $currentPeriod = TimesheetPeriod::current($this->company);
    $nextPeriod = TimesheetPeriod::next($this->company, $currentPeriod['start']);

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
        'day_rate' => 100,
    ]);

    sentDay($booking, $currentPeriod['start']->toDateString());
    sentDay($booking, $nextPeriod['start']->toDateString());

    $rows = BookingTimesheetOverview::periodRows($booking);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['days_count'])->toBe(1)
        ->and($rows[1]['days_count'])->toBe(1);
});

test('the days breakdown carries the detail needed for the popup', function () {
    $approver = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Kirsty Greaves']);

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
        'day_rate' => 150,
        'day_charge_rate' => 200,
    ]);

    sentDay($booking, TimesheetPeriod::current($this->company)['start']->toDateString(), overrides: [
        'approved_by_user_id' => $approver->id,
    ]);

    $day = BookingTimesheetOverview::periodRows($booking)[0]['days'][0];

    expect($day['pay'])->toBe('£150.00')
        ->and($day['charge'])->toBe('£200.00')
        ->and($day['margin'])->toBe('£50.00')
        ->and($day['approved_by'])->toBe('Kirsty Greaves')
        ->and($day['approved_at'])->not->toBe('—')
        ->and($day['sent_at'])->not->toBe('—');
});

test('dayPay computes full day, half day, and hourly rates correctly', function () {
    $booking = Booking::factory()->make([
        'day_rate' => 100,
        'half_day_rate' => 60,
        'hourly_rate' => 20,
    ]);

    $fullDay = new BookingDay(['period' => BookingDayPeriod::FullDay]);
    $am = new BookingDay(['period' => BookingDayPeriod::Am]);
    $hours = new BookingDay(['period' => BookingDayPeriod::Hours, 'time_from' => '09:00', 'time_to' => '13:00']);

    expect(BookingTimesheetOverview::dayPay($booking, $fullDay))->toBe(100.0)
        ->and(BookingTimesheetOverview::dayPay($booking, $am))->toBe(60.0)
        ->and(BookingTimesheetOverview::dayPay($booking, $hours))->toBe(80.0);
});

test('dayCharge and dayMargin compute full day, half day, and hourly rates correctly', function () {
    $booking = Booking::factory()->make([
        'day_rate' => 100,
        'day_charge_rate' => 140,
        'half_day_rate' => 60,
        'half_day_charge_rate' => 90,
        'hourly_rate' => 20,
        'hourly_charge_rate' => 30,
    ]);

    $fullDay = new BookingDay(['period' => BookingDayPeriod::FullDay]);
    $am = new BookingDay(['period' => BookingDayPeriod::Am]);
    $hours = new BookingDay(['period' => BookingDayPeriod::Hours, 'time_from' => '09:00', 'time_to' => '13:00']);

    expect(BookingTimesheetOverview::dayCharge($booking, $fullDay))->toBe(140.0)
        ->and(BookingTimesheetOverview::dayCharge($booking, $am))->toBe(90.0)
        ->and(BookingTimesheetOverview::dayCharge($booking, $hours))->toBe(120.0)
        ->and(BookingTimesheetOverview::dayMargin($booking, $fullDay))->toBe(40.0)
        ->and(BookingTimesheetOverview::dayMargin($booking, $am))->toBe(30.0)
        ->and(BookingTimesheetOverview::dayMargin($booking, $hours))->toBe(40.0);
});

test('the widget mounts successfully for a booking', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    Livewire::test(BookingTimesheetOverview::class, ['record' => $booking])
        ->assertSuccessful();
});

test('the timesheets tab renders on the booking edit page', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    Livewire::test(EditBooking::class, ['record' => $booking->id])
        ->assertSuccessful()
        ->assertSee('Timesheets');
});
