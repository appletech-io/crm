<?php

use App\Filament\Resources\HealthcareCandidates\Pages\ListHealthcareCandidates;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
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

    $industry = Industry::firstOrCreate(['slug' => 'healthcare'], ['name' => 'Healthcare']);
    Cache::put("user.{$this->user->id}.active_industry", $industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $industry->id);
});

test('creating a candidate requires a first and last name', function () {
    Livewire::test(ListHealthcareCandidates::class)
        ->callAction('create', data: [
            'email' => 'jane.doe@example.com',
        ])
        ->assertHasActionErrors(['first_name', 'last_name']);

    expect(HealthcareCandidate::where('email', 'jane.doe@example.com')->exists())->toBeFalse();
});

test('creating a candidate with a first and last name succeeds', function () {
    Livewire::test(ListHealthcareCandidates::class)
        ->callAction('create', data: [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.com',
        ])
        ->assertHasNoActionErrors();

    $candidate = HealthcareCandidate::where('email', 'jane.doe@example.com')->first();

    expect($candidate)->not->toBeNull();
    expect($candidate->first_name)->toBe('Jane');
    expect($candidate->last_name)->toBe('Doe');
});

test('creating a candidate attaches the logged in user as their consultant', function () {
    Livewire::test(ListHealthcareCandidates::class)
        ->callAction('create', data: [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.com',
        ])
        ->assertHasNoActionErrors();

    $candidate = HealthcareCandidate::where('email', 'jane.doe@example.com')->first();

    expect($candidate->consultant_id)->toBe($this->user->id);
});
