<?php

use App\Filament\Resources\CandidateStatuses\Pages\ManageCandidateStatusAutomations;
use App\Models\CandidateStatus;
use App\Models\CandidateStatusAutomation;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
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
});

test('can create an automation with a filled condition from the suggestion list', function () {
    $onboarding = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Onboarding',
    ]);

    $vetting = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Vetting',
    ]);

    Livewire::test(ManageCandidateStatusAutomations::class)
        ->callAction('create', data: [
            'candidate_status_id' => $onboarding->id,
            'to_candidate_status_id' => $vetting->id,
            'conditions' => [
                'item-1' => ['field' => 'application.completed_at', 'operator' => 'filled'],
            ],
        ])
        ->assertHasNoActionErrors();

    $automation = CandidateStatusAutomation::where('candidate_status_id', $onboarding->id)->first();

    expect($automation)->not->toBeNull();
    expect($automation->conditions)->toBe([
        ['field' => 'application.completed_at', 'operator' => 'filled'],
    ]);
});

test('can create an automation with an equals condition and a value', function () {
    $onboarding = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Onboarding',
    ]);

    $vetting = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Vetting',
    ]);

    Livewire::test(ManageCandidateStatusAutomations::class)
        ->callAction('create', data: [
            'candidate_status_id' => $onboarding->id,
            'to_candidate_status_id' => $vetting->id,
            'conditions' => [
                'item-1' => ['field' => 'first_name', 'operator' => 'equals', 'value' => 'Jane'],
            ],
        ])
        ->assertHasNoActionErrors();

    $automation = CandidateStatusAutomation::where('candidate_status_id', $onboarding->id)->first();

    expect($automation)->not->toBeNull();
    expect($automation->conditions)->toBe([
        ['field' => 'first_name', 'operator' => 'equals', 'value' => 'Jane'],
    ]);
});

test('can create an automation with a healthcare relation column field from the suggestion list', function () {
    $healthcareIndustry = Industry::factory()->create(['slug' => 'healthcare']);
    Cache::put("user.{$this->user->id}.active_industry", $healthcareIndustry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $healthcareIndustry->id);

    $onboarding = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $healthcareIndustry->id,
        'name' => 'Onboarding',
    ]);

    $vetting = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $healthcareIndustry->id,
        'name' => 'Vetting',
    ]);

    Livewire::test(ManageCandidateStatusAutomations::class)
        ->callAction('create', data: [
            'candidate_status_id' => $onboarding->id,
            'to_candidate_status_id' => $vetting->id,
            'conditions' => [
                'item-1' => ['field' => 'application.email_verified', 'operator' => 'filled'],
            ],
        ])
        ->assertHasNoActionErrors();

    $automation = CandidateStatusAutomation::where('candidate_status_id', $onboarding->id)->first();

    expect($automation)->not->toBeNull();
    expect($automation->conditions)->toBe([
        ['field' => 'application.email_verified', 'operator' => 'filled'],
    ]);
});

test('healthcare candidate field suggestions include own columns, application fields and to-many relations', function () {
    $suggestions = HealthcareCandidate::candidateFieldSuggestions();

    expect($suggestions)->toHaveKey('first_name')
        ->toHaveKey('email')
        ->toHaveKey('application.email_verified')
        ->toHaveKey('application.status')
        ->toHaveKey('skills.*')
        ->not->toHaveKey('id')
        ->not->toHaveKey('company_id')
        ->not->toHaveKey('application.candidate_id')
        ->not->toHaveKey('application.candidate_type');

    expect($suggestions['first_name'])->toBe(['label' => 'First Name', 'type' => 'string']);
    expect($suggestions['application.email_verified'])->toBe(['label' => 'Application: Email Verified', 'type' => 'boolean']);
    expect($suggestions['skills.*'])->toBe(['label' => 'Skills', 'type' => 'relation_exists']);
});

test('cannot create an automation with a field that is not in the suggestion list', function () {
    $onboarding = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Onboarding',
    ]);

    $vetting = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Vetting',
    ]);

    Livewire::test(ManageCandidateStatusAutomations::class)
        ->callAction('create', data: [
            'candidate_status_id' => $onboarding->id,
            'to_candidate_status_id' => $vetting->id,
            'conditions' => [
                'item-1' => ['field' => 'made_up_field_that_does_not_exist', 'operator' => 'filled'],
            ],
        ])
        ->assertHasActionErrors(['conditions.item-1.field']);
});

test('cannot create an equals condition without a value', function () {
    $onboarding = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Onboarding',
    ]);

    $vetting = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Vetting',
    ]);

    Livewire::test(ManageCandidateStatusAutomations::class)
        ->callAction('create', data: [
            'candidate_status_id' => $onboarding->id,
            'to_candidate_status_id' => $vetting->id,
            'conditions' => [
                'item-1' => ['field' => 'first_name', 'operator' => 'equals', 'value' => ''],
            ],
        ])
        ->assertHasActionErrors(['conditions.item-1.value']);
});
