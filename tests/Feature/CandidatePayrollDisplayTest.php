<?php

use App\Enums\Integration;
use App\Enums\PaymentMethod;
use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Filament\Resources\HealthcareCandidates\Pages\EditHealthcareCandidate;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\PaymentProvider;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    // This file is only about the read-only display, not payroll sync
    // behaviour — faked so saving a candidate below doesn't try to actually
    // reach Evertime (no api_url/api_key is configured here).
    Queue::fake();

    $this->company = Company::factory()->create(['payroll_provider' => Integration::Evertime->value]);
    $this->admin = User::factory()->create(['company_id' => $this->company->id]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
});

test('the education candidate edit page shows the umbrella company and payroll provider id, both read-only', function () {
    $industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($industry);
    Cache::put("user.{$this->admin->id}.active_industry", $industry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $industry->id);

    $paymentProvider = PaymentProvider::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Orbital Umbrella Ltd',
    ]);
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'payment_method' => PaymentMethod::Umbrella->value,
        'payment_provider_id' => $paymentProvider->id,
    ]);
    $candidate->setProviderExternalId(Integration::Evertime, 'EVERTIME-CANDIDATE-1');

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertFormFieldIsHidden('payroll_provider_id')
        ->assertSee('Orbital Umbrella Ltd')
        ->assertSee('EVERTIME-CANDIDATE-1');
});

test('the education candidate edit page shows placeholders when there is no umbrella company or synced id yet', function () {
    $industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($industry);
    Cache::put("user.{$this->admin->id}.active_industry", $industry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $industry->id);

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'payment_method' => PaymentMethod::Paye->value,
        'payment_provider_id' => null,
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('Not yet synced');
});

test('the payroll provider id still shows even when the company has no active payroll provider configured', function () {
    $this->company->update(['payroll_provider' => null]);

    $industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($industry);
    Cache::put("user.{$this->admin->id}.active_industry", $industry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $industry->id);

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'payment_method' => PaymentMethod::Paye->value,
    ]);
    $candidate->setProviderExternalId(Integration::Evertime, 'EVERTIME-PRE-SYNCED');

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('EVERTIME-PRE-SYNCED');
});

test('the healthcare candidate edit page shows the umbrella company and payroll provider id, both read-only', function () {
    $industry = Industry::factory()->create(['slug' => 'healthcare']);
    $this->company->industries()->attach($industry);
    Cache::put("user.{$this->admin->id}.active_industry", $industry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $industry->id);

    $paymentProvider = PaymentProvider::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Nightingale Payroll Ltd',
    ]);
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->company->id,
        'payment_method' => PaymentMethod::Umbrella->value,
        'payment_provider_id' => $paymentProvider->id,
    ]);
    $candidate->setProviderExternalId(Integration::Evertime, 'EVERTIME-CANDIDATE-2');

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertFormFieldIsHidden('payroll_provider_id')
        ->assertSee('Nightingale Payroll Ltd')
        ->assertSee('EVERTIME-CANDIDATE-2');
});
