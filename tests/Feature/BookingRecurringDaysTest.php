<?php

use App\Enums\BookingDayPeriod;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Schemas\BookingForm;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->client = Client::factory()->create(['company_id' => $this->user->company_id]);
    $this->candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $this->jobTitle = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);
});

test('dayPeriodsForRange with no weekday filter includes every consecutive day, as before', function () {
    $dates = collect(BookingForm::dayPeriodsForRange('2026-09-01', '2026-09-07'))->pluck('date');

    expect($dates->all())->toBe([
        '2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04', '2026-09-05', '2026-09-06', '2026-09-07',
    ]);
});

test('dayPeriodsForRange only includes the selected weekdays', function () {
    // 2026-09-01 is a Tuesday, so this range covers two full weeks.
    $dates = collect(BookingForm::dayPeriodsForRange('2026-09-01', '2026-09-14', [], ['4', '5']))
        ->pluck('date');

    expect($dates->all())->toBe([
        '2026-09-03', // Thursday
        '2026-09-04', // Friday
        '2026-09-10', // Thursday
        '2026-09-11', // Friday
    ]);
});

test('dayPeriodsForRange preserves an existing entrys period and cancelled state for a still-included date', function () {
    $result = BookingForm::dayPeriodsForRange('2026-09-03', '2026-09-04', [
        ['date' => '2026-09-03', 'period' => BookingDayPeriod::Am->value, 'time_from' => null, 'time_to' => null, 'cancelled' => true],
    ], ['4']);

    expect($result)->toBe([
        ['date' => '2026-09-03', 'period' => 'am', 'time_from' => null, 'time_to' => null, 'cancelled' => true],
    ]);
});

test('dayPeriodsForRange automatically marks freshly generated Saturdays and Sundays as N/A', function () {
    // 2026-09-01 is a Tuesday, so this range covers one Saturday (5th) and one Sunday (6th).
    $result = collect(BookingForm::dayPeriodsForRange('2026-09-01', '2026-09-07'))
        ->mapWithKeys(fn (array $entry): array => [$entry['date'] => $entry['cancelled']]);

    expect($result->all())->toBe([
        '2026-09-01' => false,
        '2026-09-02' => false,
        '2026-09-03' => false,
        '2026-09-04' => false,
        '2026-09-05' => true,
        '2026-09-06' => true,
        '2026-09-07' => false,
    ]);
});

test('dayPeriodsForRange does not default weekends to N/A for a healthcare consultant', function () {
    $healthcareIndustry = Industry::factory()->create(['slug' => 'healthcare']);
    Cache::put("user.{$this->user->id}.active_industry", $healthcareIndustry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $healthcareIndustry->id);

    // 2026-09-01 is a Tuesday, so this range covers one Saturday (5th) and one Sunday (6th).
    $result = collect(BookingForm::dayPeriodsForRange('2026-09-01', '2026-09-07'))
        ->mapWithKeys(fn (array $entry): array => [$entry['date'] => $entry['cancelled']]);

    expect($result->all())->toBe([
        '2026-09-01' => false,
        '2026-09-02' => false,
        '2026-09-03' => false,
        '2026-09-04' => false,
        '2026-09-05' => false,
        '2026-09-06' => false,
        '2026-09-07' => false,
    ]);
});

test('dayPeriodsForRange respects an explicit weekendsDefaultToNA override regardless of active_industry', function () {
    // active_industry is 'education' per beforeEach, but the explicit false
    // here must win — this is what the client portal relies on.
    $result = collect(BookingForm::dayPeriodsForRange('2026-09-01', '2026-09-07', weekendsDefaultToNA: false))
        ->mapWithKeys(fn (array $entry): array => [$entry['date'] => $entry['cancelled']]);

    expect($result->get('2026-09-05'))->toBeFalse()
        ->and($result->get('2026-09-06'))->toBeFalse();
});

test('dayPeriodsForRange does not override an existing weekend entry that was manually included', function () {
    $result = BookingForm::dayPeriodsForRange('2026-09-05', '2026-09-05', [
        ['date' => '2026-09-05', 'period' => BookingDayPeriod::FullDay->value, 'time_from' => null, 'time_to' => null, 'cancelled' => false],
    ]);

    expect($result)->toBe([
        ['date' => '2026-09-05', 'period' => 'full_day', 'time_from' => null, 'time_to' => null, 'cancelled' => false],
    ]);
});

test('selecting only Thursday and Friday generates a booking that skips the other weekdays', function () {
    Livewire::test(CreateBooking::class)
        ->fillForm([
            'candidate_id' => $this->candidate->id,
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-14',
            'days_of_week' => ['4', '5'],
        ])
        ->assertFormSet(function (array $state): bool {
            $dates = collect($state['day_periods'])->pluck('date')->all();

            return $dates === ['2026-09-03', '2026-09-04', '2026-09-10', '2026-09-11'];
        });
});

test('withPeriodAppliedToSelected only changes the period for ticked rows', function () {
    $result = BookingForm::withPeriodAppliedToSelected([
        ['date' => '2026-09-01', 'period' => 'full_day', 'selected' => true],
        ['date' => '2026-09-02', 'period' => 'full_day', 'selected' => false],
    ], BookingDayPeriod::Am);

    expect($result[0]['period'])->toBe('am')
        ->and($result[1]['period'])->toBe('full_day');
});

test('withCancelledAppliedToSelected only changes cancelled for ticked rows', function () {
    $result = BookingForm::withCancelledAppliedToSelected([
        ['date' => '2026-09-01', 'cancelled' => false, 'selected' => true],
        ['date' => '2026-09-02', 'cancelled' => false, 'selected' => false],
    ], true);

    expect($result[0]['cancelled'])->toBeTrue()
        ->and($result[1]['cancelled'])->toBeFalse();
});

test('withPeriodAppliedToSelected and withCancelledAppliedToSelected leave rows unchanged when nothing is selected', function () {
    $rows = [
        ['date' => '2026-09-01', 'period' => 'full_day', 'cancelled' => false, 'selected' => false],
    ];

    expect(BookingForm::withPeriodAppliedToSelected($rows, BookingDayPeriod::Hours))->toBe($rows)
        ->and(BookingForm::withCancelledAppliedToSelected($rows, true))->toBe([
            ['date' => '2026-09-01', 'period' => 'full_day', 'cancelled' => false, 'selected' => false],
        ]);
});
