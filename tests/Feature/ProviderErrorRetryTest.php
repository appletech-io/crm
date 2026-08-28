<?php

use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Enums\Integration;
use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Jobs\SyncPayrollProviderRecord;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\ProviderError;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    // This file is only about the retry action on a booking's own provider
    // error, not the independent client/candidate sync — faked so creating
    // them below doesn't reach Evertime for real. Scoped to just this one
    // job class (rather than a bare Queue::fake()) because dispatchSync()
    // — used by the retry action itself — still routes ShouldQueue jobs
    // through the queue resolver, so a blanket fake would swallow the retry
    // action's own dispatch too. No Http::fake() here — each test registers
    // its own, and an earlier catch-all would only shadow it (Laravel
    // matches fakes in registration order, first hit wins).
    Bus::fake([SyncPayrollProviderRecord::class]);

    $this->company = Company::factory()->create(['payroll_provider' => Integration::Evertime->value]);
    $this->company->setIntegrationSetting(Integration::Evertime, 'api_url', 'https://api-staging.evertime.co.uk');
    $this->company->setIntegrationSetting(Integration::Evertime, 'api_key', 'test-key');

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($this->industry);

    $this->admin = User::factory()->create(['company_id' => $this->company->id]);
    $this->admin->assignRole('admin');

    Cache::put("user.{$this->admin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $this->industry->id);

    $this->client = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $this->candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'ni_number' => 'QQ123456C',
        'date_of_birth' => '1990-01-01',
        'gender' => 'female',
    ]);
    $jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    $this->booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
        'status' => BookingStatus::Approved,
        'consultant_id' => $this->admin->id,
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);
    $this->booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->toDateString(),
        'period' => BookingDayPeriod::FullDay->value,
        'payroll_confirmation_sent_at' => now(),
        'approved_at' => now(),
    ]);
});

test('the retry action is hidden when there is no provider error', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditBooking::class, ['record' => $this->booking->getRouteKey()])
        ->assertActionHidden('retryPayrollSubmission');
});

test('the retry action is visible once a provider error is recorded', function () {
    ProviderError::create([
        'company_id' => $this->company->id,
        'booking_id' => $this->booking->id,
        'provider' => Integration::Evertime->value,
        'errors' => ["The supplied VatCode of 'Standard' is invalid."],
    ]);

    $this->actingAs($this->admin);

    Livewire::test(EditBooking::class, ['record' => $this->booking->getRouteKey()])
        ->assertActionVisible('retryPayrollSubmission')
        ->assertFormFieldIsVisible('payroll_provider_errors');
});

test('a successful retry clears the provider error and hides the action', function () {
    ProviderError::create([
        'company_id' => $this->company->id,
        'booking_id' => $this->booking->id,
        'provider' => Integration::Evertime->value,
        'errors' => ["The supplied VatCode of 'Standard' is invalid."],
    ]);

    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $this->actingAs($this->admin);

    Livewire::test(EditBooking::class, ['record' => $this->booking->getRouteKey()])
        ->callAction('retryPayrollSubmission')
        ->assertNotified('Payroll submission retried successfully');

    expect(ProviderError::where('booking_id', $this->booking->id)->exists())->toBeFalse();
});

test('a failed retry keeps the provider error visible with the new message', function () {
    ProviderError::create([
        'company_id' => $this->company->id,
        'booking_id' => $this->booking->id,
        'provider' => Integration::Evertime->value,
        'errors' => ['Old error'],
    ]);

    Http::fake(['*/clients' => Http::response([
        'HasErrors' => true,
        'Errors' => [['ErrorMessage' => 'Still broken']],
    ], 422)]);

    $this->actingAs($this->admin);

    Livewire::test(EditBooking::class, ['record' => $this->booking->getRouteKey()])
        ->callAction('retryPayrollSubmission')
        ->assertNotified('Retry failed — see the error below');

    expect(ProviderError::where('booking_id', $this->booking->id)->first()->errors)->toBe(['Still broken']);
});
