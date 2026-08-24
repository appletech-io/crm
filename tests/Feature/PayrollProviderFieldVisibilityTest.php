<?php

use App\Enums\BookingStatus;
use App\Enums\Integration;
use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Filament\Resources\HealthcareCandidates\Pages\EditHealthcareCandidate;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create(['payroll_provider' => Integration::Evertime->value]);
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($this->industry);

    $this->admin = User::factory()->create(['company_id' => $this->company->id]);
    $this->admin->assignRole('admin');

    $this->consultant = User::factory()->create(['company_id' => $this->company->id]);
    $this->consultant->assignRole('consultant');

    foreach ([$this->admin, $this->consultant] as $user) {
        Cache::put("user.{$user->id}.active_industry", $this->industry->slug);
        Cache::put("user.{$user->id}.active_industry_id", $this->industry->id);
    }
});

test('the payroll provider id field on the client form is hidden from everyone, including an admin', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);
    Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
        ->assertFormFieldIsHidden('payroll_provider_id');

    $client->update(['consultant_id' => $this->consultant->id]);

    $this->actingAs($this->consultant);
    Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
        ->assertFormFieldIsHidden('payroll_provider_id');
});

test('a consultant saving the client form does not wipe an existing payroll provider id', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'name' => 'Old Name',
    ]);
    $client->setProviderExternalId(Integration::Evertime, 'PRE-EXISTING-1');

    $this->actingAs($this->consultant);
    Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
        ->fillForm(['name' => 'New Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($client->refresh()->name)->toBe('New Name');
    expect($client->providerExternalId(Integration::Evertime))->toBe('PRE-EXISTING-1');
});

test('the payroll provider id field on the education candidate form is hidden from everyone, including an admin', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->admin);
    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertFormFieldIsHidden('payroll_provider_id');

    $this->actingAs($this->consultant);
    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertFormFieldIsHidden('payroll_provider_id');
});

test('a consultant saving the education candidate form does not wipe an existing payroll provider id', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'first_name' => 'Old']);
    $candidate->setProviderExternalId(Integration::Evertime, 'PRE-EXISTING-2');

    $this->actingAs($this->consultant);
    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm(['first_name' => 'New'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($candidate->refresh()->first_name)->toBe('New');
    expect($candidate->providerExternalId(Integration::Evertime))->toBe('PRE-EXISTING-2');
});

test('the payroll provider id field on the healthcare candidate form is hidden from everyone, including an admin', function () {
    $healthcareIndustry = Industry::factory()->create(['slug' => 'healthcare']);
    $this->company->industries()->attach($healthcareIndustry);
    Cache::put("user.{$this->admin->id}.active_industry", 'healthcare');
    Cache::put("user.{$this->admin->id}.active_industry_id", $healthcareIndustry->id);
    Cache::put("user.{$this->consultant->id}.active_industry", 'healthcare');
    Cache::put("user.{$this->consultant->id}.active_industry_id", $healthcareIndustry->id);

    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->admin);
    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertFormFieldIsHidden('payroll_provider_id');

    $this->actingAs($this->consultant);
    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertFormFieldIsHidden('payroll_provider_id');
});

test('the payroll provider id field on the booking form is hidden from everyone, including an admin', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
        'status' => BookingStatus::Upcoming,
        'consultant_id' => $this->consultant->id,
    ]);

    $this->actingAs($this->admin);
    Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->assertFormFieldIsHidden('payroll_provider_id');

    $this->actingAs($this->consultant);
    Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->assertFormFieldIsHidden('payroll_provider_id');
});

test('a consultant saving the booking form does not wipe an existing payroll provider id', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
        'status' => BookingStatus::Upcoming,
        'consultant_id' => $this->consultant->id,
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);
    $booking->setProviderExternalId(Integration::Evertime, 'PRE-EXISTING-PLACEMENT');

    $this->actingAs($this->consultant);
    Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($booking->providerExternalId(Integration::Evertime))->toBe('PRE-EXISTING-PLACEMENT');
});

test('the payroll provider id field on the user form is hidden, even from a site_admin', function () {
    // UserResource::canViewAny() is site_admin-only, not just admin — a
    // regular company admin can't reach this page at all.
    $siteAdmin = User::factory()->create(['company_id' => $this->company->id]);
    $siteAdmin->assignRole('site_admin');

    $target = User::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($siteAdmin);
    Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
        ->assertFormFieldIsHidden('payroll_provider_id');

    $this->actingAs($this->consultant);
    Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
        ->assertRedirect();
});

test('saving the user form without touching the payroll provider id field keeps the existing value', function () {
    $siteAdmin = User::factory()->create(['company_id' => $this->company->id]);
    $siteAdmin->assignRole('site_admin');

    $target = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Old Name']);
    $target->setProviderExternalId(Integration::Evertime, 'PRE-EXISTING-CONSULTANT');

    $this->actingAs($siteAdmin);
    Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
        ->fillForm(['name' => 'New Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($target->refresh()->name)->toBe('New Name');
    expect($target->providerExternalId(Integration::Evertime))->toBe('PRE-EXISTING-CONSULTANT');
});
