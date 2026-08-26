<?php

use App\Enums\PaymentMethod;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
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
    $this->jobTitle = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);
});

test('a PAYE candidate has 15% employer oncosts deducted from the margin', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'payment_method' => PaymentMethod::Paye,
    ]);

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'candidate_id' => $candidate->id,
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'day_rate' => 100,
            'day_charge_rate' => 150,
        ])
        ->assertSee('PAYE')
        ->assertSee('£15.00 (15% of pay, PAYE)')
        ->assertSee('£100.00')
        ->assertSee('£150.00')
        ->assertSee('£35.00 (23.3%)');
});

test('an umbrella candidate has no employer oncosts', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'payment_method' => PaymentMethod::Umbrella,
    ]);

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'candidate_id' => $candidate->id,
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'day_rate' => 100,
            'day_charge_rate' => 150,
        ])
        ->assertSee('Umbrella')
        ->assertSee('£0.00 (umbrella company invoices their own costs)')
        ->assertSee('£50.00 (33.3%)');
});

test('cancelled days are excluded from the margin calculation', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'payment_method' => PaymentMethod::Paye,
    ]);

    $component = Livewire::test(CreateBooking::class)
        ->fillForm([
            'candidate_id' => $candidate->id,
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-02',
            'day_rate' => 100,
            'day_charge_rate' => 150,
        ]);

    $dayPeriods = $component->get('data.day_periods');
    $dayPeriods[1]['cancelled'] = true;

    $component->set('data.day_periods', $dayPeriods)
        ->assertSee('£100.00')
        ->assertSee('£150.00');
});

test('the daily breakdown shows a row per scheduled day with its own pay, charge and margin', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'payment_method' => PaymentMethod::Paye,
    ]);

    $component = Livewire::test(CreateBooking::class)
        ->fillForm([
            'candidate_id' => $candidate->id,
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-02',
            'day_rate' => 100,
            'day_charge_rate' => 150,
            'half_day_rate' => 40,
            'half_day_charge_rate' => 70,
        ]);

    $dayPeriods = $component->get('data.day_periods');
    $dayPeriods[1]['period'] = 'am';

    $component->set('data.day_periods', $dayPeriods)
        // Day one: a full day at £100/£150 pay/charge, less 15% PAYE oncost on pay -> £35.00 margin.
        ->assertSee('Tue 1 Sep 2026')
        ->assertSee('£100.00')
        ->assertSee('£150.00')
        ->assertSee('£35.00')
        // Day two: an AM half day at £40/£70, a different rate to prove each row is calculated independently.
        ->assertSee('Wed 2 Sep 2026')
        ->assertSee('£40.00')
        ->assertSee('£70.00')
        ->assertSee('£24.00');
});

test('a cancelled day does not get its own row in the daily breakdown', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'payment_method' => PaymentMethod::Paye,
    ]);

    $component = Livewire::test(CreateBooking::class)
        ->fillForm([
            'candidate_id' => $candidate->id,
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-02',
            'day_rate' => 100,
            'day_charge_rate' => 150,
        ]);

    $dayPeriods = $component->get('data.day_periods');
    $dayPeriods[1]['cancelled'] = true;

    $component->set('data.day_periods', $dayPeriods)
        ->assertSee('Tue 1 Sep 2026')
        ->assertDontSee('Wed 2 Sep 2026');
});

test('the margin calculator says plainly when the candidate has no payment method set', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'payment_method' => null,
    ]);

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'candidate_id' => $candidate->id,
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'day_rate' => 100,
            'day_charge_rate' => 150,
        ])
        ->assertSee('Not set')
        ->assertSee('£0.00 (no payment method set on the candidate)');
});
