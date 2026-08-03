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
