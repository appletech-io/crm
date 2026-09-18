<?php

use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Models\Booking;
use App\Models\Client;
use App\Models\CompanyIndustry;
use App\Models\HealthcareCandidate;
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

    $this->industry = Industry::factory()->create(['slug' => 'healthcare']);
    $this->user->company->industries()->attach($this->industry->id);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->client = Client::factory()->create(['company_id' => $this->user->company_id]);
    $this->candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $this->jobTitle = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);
});

test('Sleep-In and Waking Night are not selectable on the calendar when complex_booking is off', function () {
    Livewire::test(CreateBooking::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'candidate_id' => $this->candidate->id,
            'candidate_type' => HealthcareCandidate::class,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
        ])
        ->assertDontSee('Set Sleep-In')
        ->assertDontSee('Set Waking Night');
});

test('Sleep-In and Waking Night become selectable on the calendar once complex_booking is on', function () {
    CompanyIndustry::where('company_id', $this->user->company_id)
        ->where('industry_id', $this->industry->id)
        ->update(['complex_booking' => true]);

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'candidate_id' => $this->candidate->id,
            'candidate_type' => HealthcareCandidate::class,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
        ])
        ->assertSee('Set Sleep-In')
        ->assertSee('Set Waking Night');
});

test('the Sleep-In and Waking Night rate fields stay hidden even with those periods present when complex_booking is off', function () {
    Livewire::test(CreateBooking::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'candidate_id' => $this->candidate->id,
            'candidate_type' => HealthcareCandidate::class,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
        ])
        ->fillForm([
            'day_periods' => [
                ['date' => '2026-09-01', 'period' => 'sleep_in'],
            ],
        ])
        ->assertDontSee('Sleep-In Pay Rate')
        ->assertDontSee('Sleep-In Charge Rate');
});

test('a booking can be created with Sleep-In and Waking Night periods and their own rates', function () {
    CompanyIndustry::where('company_id', $this->user->company_id)
        ->where('industry_id', $this->industry->id)
        ->update(['complex_booking' => true]);

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'candidate_id' => $this->candidate->id,
            'candidate_type' => HealthcareCandidate::class,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-02',
        ])
        ->fillForm([
            'day_periods' => [
                ['date' => '2026-09-01', 'period' => 'sleep_in'],
                ['date' => '2026-09-02', 'period' => 'waking_night', 'time_from' => '20:00', 'time_to' => '23:00'],
            ],
        ])
        ->assertSee('Sleep-In Pay Rate')
        ->assertSee('Waking Night Pay Rate')
        ->fillForm([
            'sleep_in_rate' => 45,
            'sleep_in_charge_rate' => 65,
            'waking_night_rate' => 15,
            'waking_night_charge_rate' => 22,
        ])
        // 45 (Sleep-In flat) + 3 hours x £15 (Waking Night) = £90 pay;
        // 65 + 3 x £22 = £131 charge.
        ->assertSee('£90.00')
        ->assertSee('£131.00')
        ->call('create')
        ->assertHasNoFormErrors();

    $booking = Booking::first();

    expect($booking->sleep_in_rate)->toBe(45.0)
        ->and($booking->waking_night_rate)->toBe(15.0)
        ->and($booking->dayPeriods()->where('period', 'sleep_in')->exists())->toBeTrue()
        ->and($booking->dayPeriods()->where('period', 'waking_night')->exists())->toBeTrue();
});
