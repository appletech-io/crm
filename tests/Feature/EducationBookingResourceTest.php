<?php

use App\Filament\Resources\EducationBookings\Pages\CreateEducationBooking;
use App\Filament\Resources\EducationBookings\Pages\EditEducationBooking;
use App\Models\EducationBooking;
use App\Models\EducationCandidate;
use App\Models\EducationClient;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\PayRate;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
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

test('charge rates are required to create a booking', function () {
    Livewire::test(CreateEducationBooking::class)
        ->fillForm([
            'education_client_id' => $this->client->id,
            'education_candidate_id' => $this->candidate->id,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => now()->toDateString(),
            'hourly_charge_rate' => null,
            'day_charge_rate' => null,
            'half_day_charge_rate' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['hourly_charge_rate', 'day_charge_rate', 'half_day_charge_rate']);
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
            'start_date' => now()->toDateString(),
            'hourly_rate' => 30,
            'hourly_charge_rate' => 40,
            'day_charge_rate' => 320,
            'half_day_charge_rate' => 160,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $booking = EducationBooking::first();

    expect($booking->job_title_id)->toBe($this->jobTitle->id)
        ->and($booking->hourly_rate)->toBe(30.0)
        ->and($booking->day_rate)->toBe(200.0)
        ->and($booking->hourly_charge_rate)->toBe(40.0)
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
                ['date' => '2026-08-03', 'period' => 'full_day'],
                ['date' => '2026-08-04', 'period' => 'full_day'],
                ['date' => '2026-08-05', 'period' => 'full_day'],
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
                ['date' => '2026-08-03', 'period' => 'am'],
                ['date' => '2026-08-04', 'period' => 'pm'],
                ['date' => '2026-08-05', 'period' => 'full_day'],
            ],
        ]);
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

    expect($booking->day_periods)->toBe([
        ['date' => '2026-08-03', 'period' => 'am'],
        ['date' => '2026-08-04', 'period' => 'pm'],
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
