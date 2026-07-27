<?php

use App\Models\HealthcareApplication;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Services\ApplicationAccessSession;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Industry::factory()->create(['name' => 'Healthcare', 'slug' => 'healthcare']);
    $this->seed(RoleSeeder::class);
});

function makePendingHealthcareApplication(): HealthcareApplication
{
    $candidate = HealthcareCandidate::factory()->create();

    $application = HealthcareApplication::create([
        'candidate_type' => HealthcareCandidate::class,
        'candidate_id' => $candidate->id,
        'email' => $candidate->email,
        'status' => 'pending',
        'token' => Str::uuid(),
        'expires_on' => now()->addDays(7)->toDateString(),
    ]);

    ApplicationAccessSession::markVerified($application->token);

    return $application;
}

test('savePersonalDetails accepts a valid dropdown title', function () {
    $application = makePendingHealthcareApplication();

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('step', 2)
        ->set('title', 'Dr')
        ->set('first_name', 'Jane')
        ->set('last_name', 'Doe')
        ->call('savePersonalDetails')
        ->assertHasNoErrors();

    expect($application->candidate->fresh()->title)->toBe('Dr');
});

test('savePersonalDetails rejects a title that is not one of the dropdown options', function () {
    $application = makePendingHealthcareApplication();

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('step', 2)
        ->set('title', 'Sir')
        ->set('first_name', 'Jane')
        ->set('last_name', 'Doe')
        ->call('savePersonalDetails')
        ->assertHasErrors(['title']);
});

test('savePersonalDetails allows title to be left blank', function () {
    $application = makePendingHealthcareApplication();

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('step', 2)
        ->set('title', null)
        ->set('first_name', 'Jane')
        ->set('last_name', 'Doe')
        ->call('savePersonalDetails')
        ->assertHasNoErrors();
});
