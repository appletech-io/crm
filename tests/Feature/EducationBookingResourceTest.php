<?php

use App\Enums\BookingDayPeriod;
use App\Filament\Resources\EducationBookings\Pages\CreateEducationBooking;
use App\Filament\Resources\EducationBookings\Pages\EditEducationBooking;
use App\Filament\Resources\EducationBookings\Pages\ListEducationBookings;
use App\Jobs\GenerateBookingConfirmationPdf;
use App\Jobs\SendBookingConfirmationEmail;
use App\Jobs\SendClientBookingConfirmationEmail;
use App\Models\EducationBooking;
use App\Models\EducationBookingDayPeriod;
use App\Models\EducationCandidate;
use App\Models\EducationClient;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\PayRate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->client = EducationClient::factory()->create(['company_id' => $this->user->company_id]);
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

    Livewire::test(CreateEducationBooking::class)
        ->fillForm([
            'education_candidate_id' => $this->candidate->id,
            'job_title_id' => $this->jobTitle->id,
        ])
        ->assertFormSet([
            'hourly_rate' => 25,
            'day_rate' => 200,
            'half_day_rate' => 100,
        ]);
});

test('pay rate fields are blank when the candidate has no pay rate for the job title', function () {
    Livewire::test(CreateEducationBooking::class)
        ->fillForm([
            'education_candidate_id' => $this->candidate->id,
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
        'model_type' => EducationClient::class,
        'model_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'hourly_rate' => 40,
        'day_rate' => 320,
        'half_day_rate' => 160,
    ]);

    Livewire::test(CreateEducationBooking::class)
        ->fillForm([
            'education_client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
        ])
        ->assertFormSet([
            'hourly_charge_rate' => 40,
            'day_charge_rate' => 320,
            'half_day_charge_rate' => 160,
        ]);
});

test('charge rate fields are blank when the client has no charge rate for the job title', function () {
    Livewire::test(CreateEducationBooking::class)
        ->fillForm([
            'education_client_id' => $this->client->id,
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
        'model_type' => EducationClient::class,
        'model_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'hourly_rate' => 40,
        'day_rate' => 320,
        'half_day_rate' => 160,
    ]);

    Livewire::test(CreateEducationBooking::class)
        ->fillForm([
            'education_candidate_id' => $this->candidate->id,
            'education_client_id' => $this->client->id,
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
    Livewire::test(CreateEducationBooking::class)
        ->fillForm([
            'education_client_id' => $this->client->id,
            'education_candidate_id' => $this->candidate->id,
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

    Livewire::test(CreateEducationBooking::class)
        ->fillForm([
            'education_client_id' => $this->client->id,
            'education_candidate_id' => $this->candidate->id,
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

    $booking = EducationBooking::first();

    expect($booking->job_title_id)->toBe($this->jobTitle->id)
        ->and($booking->day_rate)->toBe(200.0)
        ->and($booking->day_charge_rate)->toBe(320.0)
        ->and($booking->half_day_charge_rate)->toBe(160.0);
});

test('setting the date range generates a day period entry for each day defaulting to full day', function () {
    Livewire::test(CreateEducationBooking::class)
        ->fillForm([
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-05',
        ])
        ->assertFormSet([
            'day_periods' => [
                ['date' => '2026-08-03', 'period' => 'full_day', 'time_from' => null, 'time_to' => null],
                ['date' => '2026-08-04', 'period' => 'full_day', 'time_from' => null, 'time_to' => null],
                ['date' => '2026-08-05', 'period' => 'full_day', 'time_from' => null, 'time_to' => null],
            ],
        ]);
});

test('extending the date range preserves already-chosen day periods', function () {
    Livewire::test(CreateEducationBooking::class)
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
                ['date' => '2026-08-03', 'period' => 'am', 'time_from' => null, 'time_to' => null],
                ['date' => '2026-08-04', 'period' => 'pm', 'time_from' => null, 'time_to' => null],
                ['date' => '2026-08-05', 'period' => 'full_day', 'time_from' => null, 'time_to' => null],
            ],
        ]);
});

test('day rate fields are visible and half day rate fields are hidden before any dates are set', function () {
    Livewire::test(CreateEducationBooking::class)
        ->assertFormFieldIsVisible('day_rate')
        ->assertFormFieldIsVisible('day_charge_rate')
        ->assertFormFieldIsHidden('half_day_rate')
        ->assertFormFieldIsHidden('half_day_charge_rate');
});

test('hourly rate fields are always hidden', function () {
    Livewire::test(CreateEducationBooking::class)
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
    Livewire::test(CreateEducationBooking::class)
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
    Livewire::test(CreateEducationBooking::class)
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
    Livewire::test(CreateEducationBooking::class)
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
    Livewire::test(CreateEducationBooking::class)
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
    Livewire::test(CreateEducationBooking::class)
        ->fillForm([
            'education_client_id' => $this->client->id,
            'education_candidate_id' => $this->candidate->id,
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

    Livewire::test(CreateEducationBooking::class)
        ->fillForm([
            'education_client_id' => $this->client->id,
            'education_candidate_id' => $this->candidate->id,
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

    $booking = EducationBooking::first();
    $dayPeriod = $booking->dayPeriods()->first();

    expect($dayPeriod->period)->toBe(BookingDayPeriod::Hours)
        ->and(Carbon::parse($dayPeriod->time_from)->format('H:i'))->toBe('09:00')
        ->and(Carbon::parse($dayPeriod->time_to)->format('H:i'))->toBe('13:00')
        ->and($booking->hourly_rate)->toBe(25.0)
        ->and($booking->hourly_charge_rate)->toBe(40.0);
});

test('a booking can be created with custom am/pm day periods', function () {
    Livewire::test(CreateEducationBooking::class)
        ->fillForm([
            'education_client_id' => $this->client->id,
            'education_candidate_id' => $this->candidate->id,
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

    $booking = EducationBooking::first();

    $dayPeriods = $booking->dayPeriods()->get()->mapWithKeys(fn (EducationBookingDayPeriod $period): array => [
        $period->date->toDateString() => $period->period->value,
    ]);

    expect($dayPeriods->all())->toBe([
        '2026-08-03' => 'am',
        '2026-08-04' => 'pm',
    ]);
});

test('edit page renders with the new fields', function () {
    $booking = EducationBooking::factory()->create([
        'company_id' => $this->user->company_id,
        'education_client_id' => $this->client->id,
        'education_candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    Livewire::test(EditEducationBooking::class, ['record' => $booking->getRouteKey()])
        ->assertSuccessful()
        ->assertFormSet(['job_title_id' => $this->jobTitle->id]);
});

test('editing a booking loads its existing day periods and syncs changes back to the day periods table', function () {
    $booking = EducationBooking::factory()->create([
        'company_id' => $this->user->company_id,
        'education_client_id' => $this->client->id,
        'education_candidate_id' => $this->candidate->id,
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

    $test = Livewire::test(EditEducationBooking::class, ['record' => $booking->getRouteKey()]);

    expect(collect($test->instance()->form->getRawState()['day_periods'] ?? [])->values()->all())
        ->toBe([
            ['date' => '2026-08-03', 'period' => 'am', 'time_from' => null, 'time_to' => null],
            ['date' => '2026-08-04', 'period' => 'full_day', 'time_from' => null, 'time_to' => null],
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

test('the list page does not crash and flags the candidate as deleted when the candidate is soft-deleted', function () {
    $booking = EducationBooking::factory()->create([
        'company_id' => $this->user->company_id,
        'education_client_id' => $this->client->id,
        'education_candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $this->candidate->delete();

    Livewire::test(ListEducationBookings::class)
        ->assertSuccessful()
        ->set('activeSection', 'all')
        ->assertSuccessful()
        ->assertSee('(deleted)');

    expect($booking->fresh()->education_candidate_id)->toBe($this->candidate->id);
});

test('the edit form does not crash and flags the candidate as deleted when the candidate is soft-deleted', function () {
    $booking = EducationBooking::factory()->create([
        'company_id' => $this->user->company_id,
        'education_client_id' => $this->client->id,
        'education_candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $this->candidate->delete();

    Livewire::test(EditEducationBooking::class, ['record' => $booking->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('(deleted)');
});

test('the resend confirmation emails action dispatches pdf generation and both confirmation email jobs', function () {
    Queue::fake();

    $booking = EducationBooking::factory()->create([
        'company_id' => $this->user->company_id,
        'education_client_id' => $this->client->id,
        'education_candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    Livewire::test(EditEducationBooking::class, ['record' => $booking->getRouteKey()])
        ->callAction('resendConfirmationEmails')
        ->assertNotified();

    Queue::assertPushed(GenerateBookingConfirmationPdf::class, fn ($job) => $job->booking->is($booking));
    Queue::assertPushed(SendBookingConfirmationEmail::class, fn ($job) => $job->booking->is($booking));
    Queue::assertPushed(SendClientBookingConfirmationEmail::class, fn ($job) => $job->booking->is($booking));
});

test('the all section table can be filtered by client and by candidate', function () {
    $otherClient = EducationClient::factory()->create(['company_id' => $this->user->company_id]);
    $otherCandidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    $matchingBooking = EducationBooking::factory()->create([
        'company_id' => $this->user->company_id,
        'education_client_id' => $this->client->id,
        'education_candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $otherClientBooking = EducationBooking::factory()->create([
        'company_id' => $this->user->company_id,
        'education_client_id' => $otherClient->id,
        'education_candidate_id' => $this->candidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $otherCandidateBooking = EducationBooking::factory()->create([
        'company_id' => $this->user->company_id,
        'education_client_id' => $this->client->id,
        'education_candidate_id' => $otherCandidate->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    Livewire::test(ListEducationBookings::class)
        ->set('activeSection', 'all')
        ->filterTable('education_client_id', $this->client->id)
        ->assertCanSeeTableRecords([$matchingBooking, $otherCandidateBooking])
        ->assertCanNotSeeTableRecords([$otherClientBooking])
        ->removeTableFilter('education_client_id')
        ->filterTable('education_candidate_id', $this->candidate->id)
        ->assertCanSeeTableRecords([$matchingBooking, $otherClientBooking])
        ->assertCanNotSeeTableRecords([$otherCandidateBooking]);
});
