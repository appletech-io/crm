<?php

use App\Filament\Resources\EducationCandidates\Pages\ListEducationCandidates;
use App\Models\EducationCandidate;
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

    $industry = Industry::firstOrCreate(['slug' => 'education'], ['name' => 'Education']);
    Cache::put("user.{$this->user->id}.active_industry", $industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $industry->id);
});

test('creating a candidate requires a first and last name', function () {
    Livewire::test(ListEducationCandidates::class)
        ->callAction('create', data: [
            'email' => 'jane.doe@example.com',
        ])
        ->assertHasActionErrors(['first_name', 'last_name']);

    expect(EducationCandidate::where('email', 'jane.doe@example.com')->exists())->toBeFalse();
});

test('creating a candidate with a first and last name succeeds', function () {
    Livewire::test(ListEducationCandidates::class)
        ->callAction('create', data: [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.com',
        ])
        ->assertHasNoActionErrors();

    $candidate = EducationCandidate::where('email', 'jane.doe@example.com')->first();

    expect($candidate)->not->toBeNull();
    expect($candidate->first_name)->toBe('Jane');
    expect($candidate->last_name)->toBe('Doe');
});
