<?php

use App\Filament\Resources\HealthcareCandidates\Pages\EditHealthcareCandidate;
use App\Models\HealthcareCandidate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
    Cache::put("user.{$this->user->id}.active_industry", 'healthcare');
});

test('a UK landline number is accepted in the phone field', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'phone' => '01234 567890',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($candidate->refresh()->phone)->toBe('01234 567890');
});

test('a UK landline number is rejected in the mobile field', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'mobile' => '01234 567890',
        ])
        ->call('save')
        ->assertHasFormErrors(['mobile' => 'regex']);
});

test('a UK mobile number is accepted in the mobile field', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'mobile' => '07700900000',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($candidate->refresh()->mobile)->toBe('07700900000');
});

test('compliance expiry dates can be edited inline from the candidate edit page', function () {
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'right_to_work_type' => 'passport',
        'dbs_certificate_number' => '001234567890',
    ]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'dbs_expiry_date' => '2029-03-01',
            'right_to_work_expiry_date' => '2027-01-01',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $candidate->refresh();
    expect($candidate->dbs_expiry_date->toDateString())->toBe('2029-03-01');
    expect($candidate->right_to_work_expiry_date->toDateString())->toBe('2027-01-01');
});

test('the right to work expiry date field is hidden and not saved when right to work type is birth certificate', function () {
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'right_to_work_type' => 'birth_certificate',
    ]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertFormFieldDoesNotExist('right_to_work_expiry_date')
        ->fillForm(['dbs_expiry_date' => '2029-03-01'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($candidate->refresh()->right_to_work_expiry_date)->toBeNull();
});
