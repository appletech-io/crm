<?php

use App\Filament\Pages\ComplianceSettings;
use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    // 'generic' is the slug this session wired to Candidate::class in
    // Industry::$candidateModelMap — canAccess() on this page is keyed
    // off that mapping, not a fixed slug name.
    $this->industry = Industry::factory()->create(['slug' => 'generic']);
    $this->company->industries()->attach($this->industry);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->industry);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('page renders for a generic industry admin', function () {
    Livewire::test(ComplianceSettings::class)
        ->assertSuccessful();
});

test('this page is not accessible for the education or healthcare industries, since their compliance is fixed', function () {
    $educationIndustry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($educationIndustry);
    Cache::put("user.{$this->user->id}.active_industry", $educationIndustry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $educationIndustry->id);

    expect(ComplianceSettings::canAccess())->toBeFalse();

    $this->get('/crm/compliance-settings')->assertRedirect('/crm');

    $healthcareIndustry = Industry::factory()->create(['slug' => 'healthcare']);
    $this->company->industries()->attach($healthcareIndustry);
    Cache::put("user.{$this->user->id}.active_industry", $healthcareIndustry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $healthcareIndustry->id);

    expect(ComplianceSettings::canAccess())->toBeFalse();
});

test('a non-admin cannot access compliance settings, even for the generic industry', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id]);
    $consultant->industries()->attach($this->industry);
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    $this->get('/crm/compliance-settings')->assertRedirect('/crm');
});
