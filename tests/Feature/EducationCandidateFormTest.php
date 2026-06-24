<?php

use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Models\EducationCandidate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
});

test('edit page renders with tabs', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful();
});

test('personal details can be saved on candidate', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'gender' => 'female',
            'nationality' => 'British',
            'date_of_birth' => '1990-01-15',
            'place_of_birth' => 'London',
            'phone' => '07700900000',
            'mobile' => '07700900001',
            'postcode' => 'SW1A 1AA',
            'city' => 'London',
            'country' => 'United Kingdom',
            'emergency_contact_name' => 'John Doe',
            'emergency_contact_number' => '07700900002',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $candidate->refresh();

    expect($candidate->first_name)->toBe('Jane');
    expect($candidate->last_name)->toBe('Doe');
    expect($candidate->gender)->toBe('female');
    expect($candidate->emergency_contact_name)->toBe('John Doe');
});
