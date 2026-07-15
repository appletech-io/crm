<?php

use App\Enums\BookingDayPeriod;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Schemas\BookingForm;
use App\Jobs\GenerateBookingConfirmationPdf;
use App\Jobs\SendBookingConfirmationEmail;
use App\Jobs\SendClientBookingConfirmationEmail;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\CandidateCandidateStatus;
use App\Models\CandidateStatus;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\PayRate;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function assignCandidateStatus(EducationCandidate $candidate, Industry $industry, string $companyId, string $statusName): void
{
    $status = CandidateStatus::factory()->create([
        'company_id' => $companyId,
        'industry_id' => $industry->id,
        'name' => $statusName,
    ]);

    CandidateCandidateStatus::create([
        'model_type' => EducationCandidate::class,
        'model_id' => $candidate->id,
        'candidate_status_id' => $status->id,
    ]);
}

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

test('selecting a candidate and job title pulls through the candidate pay rate', function () {
    PayRate::factory()->create([
        'company_id' => $this->user->company_id,
        'model_type' => EducationCandidate::class,
        'model_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
        'hourly_rate' => 25,
        'day_rate' => 200,
        'half_day_rate' => 100,
    ]);

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'candidate_id' => $this->candidate->id,
            'candidate_type' => EducationCandidate::class,
            'job_title_id' => $this->jobTitle->id,
        ])
        ->assertFormSet([
            'hourly_rate' => 25,
            'day_rate' => 200,
            'half_day_rate' => 100,
        ]);
});

test('pay rate fields are blank when the candidate has no pay rate for the job title', function () {
    Livewire::test(CreateBooking::class)
        ->fillForm([
            'candidate_id' => $this->candidate->id,
            'candidate_type' => EducationCandidate::class,
            'job_title_id' => $this->jobTitle->id,
        ])
        ->assertFormSet([
            'hourly_rate' => null,
            'day_rate' => null,
            'half_day_rate' => null,
        ]);
});

test('selecting a client and job title pulls through the client charge rate', function () {
    PayRate::factory()->create([
        'company_id' => $this->user->company_id,
        'model_type' => Client::class,
        'model_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'hourly_rate' => 40,
        'day_rate' => 320,
        'half_day_rate' => 160,
    ]);

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
        ])
        ->assertFormSet([
            'hourly_charge_rate' => 40,
            'day_charge_rate' => 320,
            'half_day_charge_rate' => 160,
        ]);
});

test('charge rate fields are blank when the client has no charge rate for the job title', function () {
    Livewire::test(CreateBooking::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
        ])
        ->assertFormSet([
            'hourly_charge_rate' => null,
            'day_charge_rate' => null,
            'half_day_charge_rate' => null,
        ]);
});

test('pay rates and charge rates pull through independently for the same job title', function () {
    PayRate::factory()->create([
        'company_id' => $this->user->company_id,
        'model_type' => EducationCandidate::class,
        'model_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
        'hourly_rate' => 25,
        'day_rate' => 200,
        'half_day_rate' => 100,
    ]);

    PayRate::factory()->create([
        'company_id' => $this->user->company_id,
        'model_type' => Client::class,
        'model_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'hourly_rate' => 40,
        'day_rate' => 320,
        'half_day_rate' => 160,
    ]);

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'candidate_id' => $this->candidate->id,
            'candidate_type' => EducationCandidate::class,
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
        ])
        ->assertFormSet([
            'hourly_rate' => 25,
            'day_rate' => 200,
            'half_day_rate' => 100,
            'hourly_charge_rate' => 40,
            'day_charge_rate' => 320,
            'half_day_charge_rate' => 160,
        ]);
});

test('charge rates are required to create a booking', function () {
    Livewire::test(CreateBooking::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'candidate_id' => $this->candidate->id,
            'candidate_type' => EducationCandidate::class,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-04',
        ])
        ->fillForm([
            'day_periods' => [
                ['date' => '2026-08-03', 'period' => 'full_day'],
                ['date' => '2026-08-04', 'period' => 'am'],
            ],
        ])
        ->fillForm([
            'day_charge_rate' => null,
            'half_day_charge_rate' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['day_charge_rate', 'half_day_charge_rate']);
});

test('a booking can be created with overridden pay rates and required charge rates', function () {
    PayRate::factory()->create([
        'company_id' => $this->user->company_id,
        'model_type' => EducationCandidate::class,
        'model_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
        'hourly_rate' => 25,
        'day_rate' => 200,
        'half_day_rate' => 100,
    ]);

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'candidate_id' => $this->candidate->id,
            'candidate_type' => EducationCandidate::class,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-04',
        ])
        ->fillForm([
            'day_periods' => [
                ['date' => '2026-08-03', 'period' => 'full_day'],
                ['date' => '2026-08-04', 'period' => 'am'],
            ],
        ])
        ->fillForm([
            'day_charge_rate' => 320,
            'half_day_charge_rate' => 160,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $booking = Booking::first();

    expect($booking->job_title_id)->toBe($this->jobTitle->id)
        ->and($booking->day_rate)->toBe(200.0)
        ->and($booking->day_charge_rate)->toBe(320.0)
        ->and($booking->half_day_charge_rate)->toBe(160.0);
});

test('setting the date range generates a day period entry for each day defaulting to full day', function () {
    Livewire::test(CreateBooking::class)
        ->fillForm([
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-05',
        ])
        ->assertFormSet([
            'day_periods' => [
                ['date' => '2026-08-03', 'period' => 'full_day', 'time_from' => null, 'time_to' => null, 'cancelled' => false],
                ['date' => '2026-08-04', 'period' => 'full_day', 'time_from' => null, 'time_to' => null, 'cancelled' => false],
                ['date' => '2026-08-05', 'period' => 'full_day', 'time_from' => null, 'time_to' => null, 'cancelled' => false],
            ],
        ]);
});

test('extending the date range preserves already-chosen day periods', function () {
    Livewire::test(CreateBooking::class)
        ->fillForm([
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-04',
        ])
        ->fillForm([
            'day_periods' => [
                ['date' => '2026-08-03', 'period' => 'am'],
                ['date' => '2026-08-04', 'period' => 'pm'],
            ],
        ])
        ->fillForm([
            'end_date' => '2026-08-05',
        ])
        ->assertFormSet([
            'day_periods' => [
                ['date' => '2026-08-03', 'period' => 'am', 'time_from' => null, 'time_to' => null, 'cancelled' => false],
                ['date' => '2026-08-04', 'period' => 'pm', 'time_from' => null, 'time_to' => null, 'cancelled' => false],
                ['date' => '2026-08-05', 'period' => 'full_day', 'time_from' => null, 'time_to' => null, 'cancelled' => false],
            ],
        ]);
});

test('day rate fields are visible and half day rate fields are hidden before any dates are set', function () {
    Livewire::test(CreateBooking::class)
        ->assertFormFieldIsVisible('day_rate')
        ->assertFormFieldIsVisible('day_charge_rate')
        ->assertFormFieldIsHidden('half_day_rate')
        ->assertFormFieldIsHidden('half_day_charge_rate');
});

test('hourly rate fields are always hidden', function () {
    Livewire::test(CreateBooking::class)
        ->assertFormFieldIsHidden('hourly_rate')
        ->assertFormFieldIsHidden('hourly_charge_rate')
        ->fillForm([
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-04',
        ])
        ->fillForm([
            'day_periods' => [
                ['date' => '2026-08-03', 'period' => 'am'],
                ['date' => '2026-08-04', 'period' => 'pm'],
            ],
        ])
        ->assertFormFieldIsHidden('hourly_rate')
        ->assertFormFieldIsHidden('hourly_charge_rate');
});

test('day rate fields are visible and half day rate fields are hidden when every day is a full day', function () {
    Livewire::test(CreateBooking::class)
        ->fillForm([
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-05',
        ])
        ->assertFormFieldIsVisible('day_rate')
        ->assertFormFieldIsVisible('day_charge_rate')
        ->assertFormFieldIsHidden('half_day_rate')
        ->assertFormFieldIsHidden('half_day_charge_rate');
});

test('half day rate fields are visible and day rate fields are hidden when every day is am or pm', function () {
    Livewire::test(CreateBooking::class)
        ->fillForm([
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-04',
        ])
        ->fillForm([
            'day_periods' => [
                ['date' => '2026-08-03', 'period' => 'am'],
                ['date' => '2026-08-04', 'period' => 'pm'],
            ],
        ])
        ->assertFormFieldIsVisible('half_day_rate')
        ->assertFormFieldIsVisible('half_day_charge_rate')
        ->assertFormFieldIsHidden('day_rate')
        ->assertFormFieldIsHidden('day_charge_rate');
});

test('both day and half day rate fields are visible when the days are a mix of full day and am/pm', function () {
    Livewire::test(CreateBooking::class)
        ->fillForm([
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-04',
        ])
        ->fillForm([
            'day_periods' => [
                ['date' => '2026-08-03', 'period' => 'full_day'],
                ['date' => '2026-08-04', 'period' => 'am'],
            ],
        ])
        ->assertFormFieldIsVisible('day_rate')
        ->assertFormFieldIsVisible('day_charge_rate')
        ->assertFormFieldIsVisible('half_day_rate')
        ->assertFormFieldIsVisible('half_day_charge_rate');
});

test('hourly rate fields become visible and day/half day rate fields hide when a day is set to hours', function () {
    Livewire::test(CreateBooking::class)
        ->fillForm([
            'start_date' => '2026-08-03',
        ])
        ->assertFormFieldIsHidden('hourly_rate')
        ->assertFormFieldIsHidden('hourly_charge_rate')
        ->fillForm([
            'day_periods' => [
                ['date' => '2026-08-03', 'period' => 'hours'],
            ],
        ])
        ->assertFormFieldIsVisible('hourly_rate')
        ->assertFormFieldIsVisible('hourly_charge_rate')
        ->assertFormFieldIsHidden('day_rate')
        ->assertFormFieldIsHidden('day_charge_rate')
        ->assertFormFieldIsHidden('half_day_rate')
        ->assertFormFieldIsHidden('half_day_charge_rate');
});

test('time from and time to are required when a day period is set to hours', function () {
    Livewire::test(CreateBooking::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'candidate_id' => $this->candidate->id,
            'candidate_type' => EducationCandidate::class,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-08-03',
        ])
        ->fillForm([
            'day_periods' => [
                ['date' => '2026-08-03', 'period' => 'hours'],
            ],
        ])
        ->fillForm([
            'hourly_charge_rate' => 30,
        ])
        ->call('create')
        ->assertHasFormErrors(['day_periods.0.time_from', 'day_periods.0.time_to']);
});

test('a booking can be created with an hours day recording time from, time to, and hourly rates', function () {
    PayRate::factory()->create([
        'company_id' => $this->user->company_id,
        'model_type' => EducationCandidate::class,
        'model_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
        'hourly_rate' => 25,
    ]);

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'candidate_id' => $this->candidate->id,
            'candidate_type' => EducationCandidate::class,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-08-03',
        ])
        ->fillForm([
            'day_periods' => [
                ['date' => '2026-08-03', 'period' => 'hours', 'time_from' => '09:00', 'time_to' => '13:00'],
            ],
        ])
        ->fillForm([
            'hourly_charge_rate' => 40,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $booking = Booking::first();
    $dayPeriod = $booking->dayPeriods()->first();

    expect($dayPeriod->period)->toBe(BookingDayPeriod::Hours)
        ->and(Carbon::parse($dayPeriod->time_from)->format('H:i'))->toBe('09:00')
        ->and(Carbon::parse($dayPeriod->time_to)->format('H:i'))->toBe('13:00')
        ->and($booking->hourly_rate)->toBe(25.0)
        ->and($booking->hourly_charge_rate)->toBe(40.0);
});

test('a booking can be created with custom am/pm day periods', function () {
    Livewire::test(CreateBooking::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'candidate_id' => $this->candidate->id,
            'candidate_type' => EducationCandidate::class,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-04',
            'hourly_charge_rate' => 40,
            'day_charge_rate' => 320,
            'half_day_charge_rate' => 160,
        ])
        ->fillForm([
            'day_periods' => [
                ['date' => '2026-08-03', 'period' => 'am'],
                ['date' => '2026-08-04', 'period' => 'pm'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $booking = Booking::first();

    $dayPeriods = $booking->dayPeriods()->get()->mapWithKeys(fn (BookingDay $period): array => [
        $period->date->toDateString() => $period->period->value,
    ]);

    expect($dayPeriods->all())->toBe([
        '2026-08-03' => 'am',
        '2026-08-04' => 'pm',
    ]);
});

test('edit page renders with the new fields', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->assertSuccessful()
        ->assertFormSet(['job_title_id' => $this->jobTitle->id]);
});

test('editing a booking loads its existing day periods and syncs changes back to the day periods table', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'start_date' => '2026-08-03',
        'end_date' => '2026-08-04',
    ]);

    $booking->dayPeriods()->create([
        'company_id' => $this->user->company_id,
        'date' => '2026-08-03',
        'period' => 'am',
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->user->company_id,
        'date' => '2026-08-04',
        'period' => 'full_day',
    ]);

    $test = Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()]);

    expect(collect($test->instance()->form->getRawState()['day_periods'] ?? [])->values()->all())
        ->toBe([
            ['date' => '2026-08-03', 'period' => 'am', 'time_from' => null, 'time_to' => null, 'cancelled' => false],
            ['date' => '2026-08-04', 'period' => 'full_day', 'time_from' => null, 'time_to' => null, 'cancelled' => false],
        ]);

    $test
        ->fillForm([
            'day_periods' => [
                ['date' => '2026-08-03', 'period' => 'pm'],
            ],
        ])
        ->fillForm([
            'half_day_charge_rate' => 160,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $remaining = $booking->dayPeriods()->get();

    expect($remaining)->toHaveCount(1)
        ->and($remaining->first()->date->toDateString())->toBe('2026-08-03')
        ->and($remaining->first()->period)->toBe(BookingDayPeriod::Pm);
});

test('a day can be cancelled via the edit form and the cancellation timestamp is stored', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'start_date' => '2026-08-03',
        'end_date' => '2026-08-04',
        'day_charge_rate' => 320,
    ]);

    $booking->dayPeriods()->create([
        'company_id' => $this->user->company_id,
        'date' => '2026-08-03',
        'period' => 'full_day',
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->user->company_id,
        'date' => '2026-08-04',
        'period' => 'full_day',
    ]);

    Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->fillForm([
            'day_periods' => [
                ['date' => '2026-08-03', 'period' => 'full_day', 'cancelled' => true],
                ['date' => '2026-08-04', 'period' => 'full_day', 'cancelled' => false],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $cancelledDay = $booking->dayPeriods()->whereDate('date', '2026-08-03')->first();
    $activeDay = $booking->dayPeriods()->whereDate('date', '2026-08-04')->first();

    expect($cancelledDay->cancelled_at)->not->toBeNull()
        ->and($cancelledDay->isCancelled())->toBeTrue()
        ->and($activeDay->cancelled_at)->toBeNull()
        ->and($activeDay->isCancelled())->toBeFalse();
});

test('re-saving an already-cancelled day preserves the original cancellation timestamp', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'start_date' => '2026-08-03',
        'day_charge_rate' => 320,
    ]);

    $originalTimestamp = now()->subDays(3);

    $booking->dayPeriods()->create([
        'company_id' => $this->user->company_id,
        'date' => '2026-08-03',
        'period' => 'full_day',
        'cancelled_at' => $originalTimestamp,
    ]);

    Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->fillForm([
            'day_periods' => [
                ['date' => '2026-08-03', 'period' => 'full_day', 'cancelled' => true],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $day = $booking->dayPeriods()->whereDate('date', '2026-08-03')->first();

    expect($day->cancelled_at->toDateTimeString())->toBe($originalTimestamp->toDateTimeString());
});

test('the list page does not crash and flags the candidate as deleted when the candidate is soft-deleted', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $this->candidate->delete();

    Livewire::test(ListBookings::class)
        ->assertSuccessful()
        ->set('activeSection', 'all')
        ->assertSuccessful()
        ->assertSee('(deleted)');

    expect($booking->fresh()->candidate_id)->toBe($this->candidate->id);
});

test('the edit form does not crash and flags the candidate as deleted when the candidate is soft-deleted', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $this->candidate->delete();

    Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('(deleted)');
});

test('the resend confirmation emails action dispatches pdf generation and both confirmation email jobs', function () {
    Queue::fake();

    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->callAction('resendConfirmationEmails')
        ->assertNotified();

    Queue::assertPushed(GenerateBookingConfirmationPdf::class, fn ($job) => $job->booking->is($booking));
    Queue::assertPushed(SendBookingConfirmationEmail::class, fn ($job) => $job->booking->is($booking));
    Queue::assertPushed(SendClientBookingConfirmationEmail::class, fn ($job) => $job->booking->is($booking));
});

test('the all section table can be filtered by client and by candidate', function () {
    $otherClient = Client::factory()->create(['company_id' => $this->user->company_id]);
    $otherCandidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    $matchingBooking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $otherClientBooking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $otherClient->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $otherCandidateBooking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $otherCandidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    Livewire::test(ListBookings::class)
        ->set('activeSection', 'all')
        ->filterTable('client_id', $this->client->id)
        ->assertCanSeeTableRecords([$matchingBooking, $otherCandidateBooking])
        ->assertCanNotSeeTableRecords([$otherClientBooking])
        ->removeTableFilter('client_id')
        ->filterTable('candidate_id', $this->candidate->id)
        ->assertCanSeeTableRecords([$matchingBooking, $otherClientBooking])
        ->assertCanNotSeeTableRecords([$otherCandidateBooking]);
});

test('creating a booking copies the consultant_id from the selected candidate', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $this->candidate->update(['consultant_id' => $consultant->id]);

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'candidate_id' => $this->candidate->id,
            'candidate_type' => EducationCandidate::class,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-04',
        ])
        ->fillForm([
            'day_periods' => [
                ['date' => '2026-08-03', 'period' => 'full_day'],
                ['date' => '2026-08-04', 'period' => 'am'],
            ],
        ])
        ->fillForm([
            'day_charge_rate' => 320,
            'half_day_charge_rate' => 160,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Booking::first()->consultant_id)->toBe($consultant->id);
});

test('editing a booking re-syncs consultant_id when the candidate changes', function () {
    $originalConsultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $newConsultant = User::factory()->create(['company_id' => $this->user->company_id]);

    $this->candidate->update(['consultant_id' => $originalConsultant->id]);
    $otherCandidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'consultant_id' => $newConsultant->id,
    ]);

    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'consultant_id' => $originalConsultant->id,
        'day_charge_rate' => 320,
    ]);

    Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->fillForm([
            'candidate_id' => $otherCandidate->id,
            'candidate_type' => EducationCandidate::class,
            'day_charge_rate' => 320,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($booking->fresh()->consultant_id)->toBe($newConsultant->id);
});

test('a non-admin user only sees bookings assigned to their own consultant_id', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $ownBooking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'consultant_id' => $consultant->id,
    ]);

    $otherConsultantBooking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'consultant_id' => $this->user->id,
    ]);

    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);

    Livewire::test(ListBookings::class)
        ->set('activeSection', 'all')
        ->assertCanSeeTableRecords([$ownBooking])
        ->assertCanNotSeeTableRecords([$otherConsultantBooking]);
});

test('an admin sees all bookings regardless of consultant_id', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);

    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'consultant_id' => $consultant->id,
    ]);

    Livewire::test(ListBookings::class)
        ->set('activeSection', 'all')
        ->assertCanSeeTableRecords([$booking]);
});

test('the consultant filter is only visible to admins', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    Livewire::test(ListBookings::class)
        ->set('activeSection', 'all')
        ->assertTableFilterVisible('consultant_id');

    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);

    Livewire::test(ListBookings::class)
        ->set('activeSection', 'all')
        ->assertTableFilterHidden('consultant_id');
});

test('the create form only offers candidates with a Live status', function () {
    assignCandidateStatus($this->candidate, $this->industry, $this->user->company_id, 'Live');

    $dnuCandidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignCandidateStatus($dnuCandidate, $this->industry, $this->user->company_id, 'DNU');

    $offlineCandidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignCandidateStatus($offlineCandidate, $this->industry, $this->user->company_id, 'Offline');

    $onboardingCandidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignCandidateStatus($onboardingCandidate, $this->industry, $this->user->company_id, 'Onboarding');

    $vettingCandidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignCandidateStatus($vettingCandidate, $this->industry, $this->user->company_id, 'Vetting');

    $noStatusCandidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(CreateBooking::class)
        ->assertFormFieldExists('candidate_id', function ($field) use ($dnuCandidate, $offlineCandidate, $onboardingCandidate, $vettingCandidate, $noStatusCandidate) {
            $options = $field->getOptions();

            return array_key_exists($this->candidate->id, $options)
                && ! array_key_exists($dnuCandidate->id, $options)
                && ! array_key_exists($offlineCandidate->id, $options)
                && ! array_key_exists($onboardingCandidate->id, $options)
                && ! array_key_exists($vettingCandidate->id, $options)
                && ! array_key_exists($noStatusCandidate->id, $options);
        });
});

test('the edit form still shows the bookings existing candidate even if no longer Live', function () {
    assignCandidateStatus($this->candidate, $this->industry, $this->user->company_id, 'Live');

    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $this->candidate->statuses()->delete();
    assignCandidateStatus($this->candidate, $this->industry, $this->user->company_id, 'DNU');

    Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->assertFormFieldExists('candidate_id', function ($field) {
            return array_key_exists($this->candidate->id, $field->getOptions());
        });
});

test('an approved booking cannot be edited and hides the resend confirmation emails action', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'status' => 'approved',
    ]);

    Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->assertFormFieldDisabled('status')
        ->assertFormFieldDisabled('candidate_id')
        ->assertActionHidden('resendConfirmationEmails');
});

test('an upcoming booking can still be edited and shows the resend confirmation emails action', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'status' => 'upcoming',
    ]);

    Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->assertFormFieldEnabled('status')
        ->assertFormFieldEnabled('candidate_id')
        ->assertActionVisible('resendConfirmationEmails');
});

test('the create form is prefilled from the query string with candidate, client, job title, dates, and rates', function () {
    PayRate::factory()->create([
        'company_id' => $this->user->company_id,
        'model_type' => EducationCandidate::class,
        'model_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
        'hourly_rate' => 25,
        'day_rate' => 200,
        'half_day_rate' => 100,
    ]);

    PayRate::factory()->create([
        'company_id' => $this->user->company_id,
        'model_type' => Client::class,
        'model_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'hourly_rate' => 40,
        'day_rate' => 300,
        'half_day_rate' => 150,
    ]);

    $date = now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();

    Livewire::withQueryParams([
        'candidate_id' => $this->candidate->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'start_date' => $date,
    ])->test(CreateBooking::class)
        ->assertFormSet([
            'candidate_id' => $this->candidate->id,
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => $date,
            'end_date' => $date,
            'status' => 'upcoming',
            'hourly_rate' => 25,
            'day_rate' => 200,
            'half_day_rate' => 100,
            'hourly_charge_rate' => 40,
            'day_charge_rate' => 300,
            'half_day_charge_rate' => 150,
        ]);
});

test('the create form falls back to normal defaults when the query string has no prefill values', function () {
    Livewire::test(CreateBooking::class)
        ->assertFormSet([
            'status' => 'upcoming',
            'candidate_id' => null,
            'client_id' => null,
        ]);
});

test('creating a booking with a full day that overlaps an existing full day booking for the same candidate fails validation', function () {
    $existingBooking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $existingBooking->dayPeriods()->create([
        'company_id' => $this->user->company_id,
        'date' => '2026-08-03',
        'period' => 'full_day',
    ]);

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'candidate_id' => $this->candidate->id,
            'candidate_type' => EducationCandidate::class,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-08-03',
            'day_charge_rate' => 320,
        ])
        ->call('create')
        ->assertHasFormErrors(['day_periods']);

    expect(Booking::count())->toBe(1);
});

test('creating a booking for a different candidate on the same dates does not fail validation', function () {
    $otherCandidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    $existingBooking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $otherCandidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $existingBooking->dayPeriods()->create([
        'company_id' => $this->user->company_id,
        'date' => '2026-08-03',
        'period' => 'full_day',
    ]);

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'candidate_id' => $this->candidate->id,
            'candidate_type' => EducationCandidate::class,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-08-03',
            'day_charge_rate' => 320,
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});

test('am and pm bookings on the same day for the same candidate do not conflict', function () {
    $existingBooking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $existingBooking->dayPeriods()->create([
        'company_id' => $this->user->company_id,
        'date' => '2026-08-03',
        'period' => 'am',
    ]);

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'candidate_id' => $this->candidate->id,
            'candidate_type' => EducationCandidate::class,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-08-03',
            'half_day_charge_rate' => 160,
        ])
        ->fillForm([
            'day_periods' => [
                ['date' => '2026-08-03', 'period' => 'pm'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});

test('two am bookings on the same day for the same candidate do conflict', function () {
    $existingBooking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $existingBooking->dayPeriods()->create([
        'company_id' => $this->user->company_id,
        'date' => '2026-08-03',
        'period' => 'am',
    ]);

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'candidate_id' => $this->candidate->id,
            'candidate_type' => EducationCandidate::class,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-08-03',
            'half_day_charge_rate' => 160,
        ])
        ->fillForm([
            'day_periods' => [
                ['date' => '2026-08-03', 'period' => 'am'],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['day_periods']);
});

test('editing a booking excludes itself from the overlap check', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'day_charge_rate' => 320,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->user->company_id,
        'date' => '2026-08-03',
        'period' => 'full_day',
    ]);

    Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->assertHasNoFormErrors();
});

test('editing a booking into a conflict with another booking for the same candidate fails validation', function () {
    $otherBooking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $otherBooking->dayPeriods()->create([
        'company_id' => $this->user->company_id,
        'date' => '2026-08-03',
        'period' => 'full_day',
    ]);

    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-10',
        'day_charge_rate' => 320,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->user->company_id,
        'date' => '2026-08-10',
        'period' => 'full_day',
    ]);

    Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->fillForm([
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
        ])
        ->call('save')
        ->assertHasFormErrors(['day_periods']);
});

test('the candidate select resolves candidates via the active industry rather than a hardcoded model', function () {
    assignCandidateStatus($this->candidate, $this->industry, $this->user->company_id, 'Live');

    Livewire::test(CreateBooking::class)
        ->assertFormFieldExists('candidate_id', function ($field): bool {
            return array_key_exists($this->candidate->id, $field->getOptions());
        });
});

test('candidate pay rate lookups are skipped when the active industry has no registered candidate model', function () {
    Cache::put("user.{$this->user->id}.active_industry", 'unknown-industry');
    Cache::forget("user.{$this->user->id}.active_industry_id");

    expect(Industry::candidateModelForSlug(active_industry() ?? ''))->toBeNull();

    // Candidate-side rate keys are omitted entirely since no candidate model could be resolved;
    // client-side charge rate lookups are unaffected since they don't depend on candidate type.
    expect(BookingForm::defaultRates($this->candidate->id, $this->client->id, $this->jobTitle->id))
        ->toBe([
            'day_charge_rate' => null,
            'half_day_charge_rate' => null,
            'hourly_charge_rate' => null,
        ]);
});
