<?php

use App\Jobs\GenerateFormattedCv;
use App\Models\HealthcareApplication;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Services\ApplicationAccessSession;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    Industry::factory()->create(['name' => 'Healthcare', 'slug' => 'healthcare']);
    $this->seed(RoleSeeder::class);
    // Uploading a CV synchronously dispatches formatted-CV generation (real
    // AI call) — faked by default so tests unrelated to that feature don't
    // need to know about it.
    Bus::fake();
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

test('saveCv stores the document and dispatches formatted CV generation', function () {
    $application = makePendingHealthcareApplication();
    $file = UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf');

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('cv', $file)
        ->call('saveCv')
        ->assertHasNoErrors()
        ->assertSet('step', 2);

    $candidate = $application->fresh()->candidate;
    expect($candidate->documents()->where('document_type', 'cv')->exists())->toBeTrue();

    Bus::assertDispatched(
        GenerateFormattedCv::class,
        fn (GenerateFormattedCv $job): bool => $job->candidate->is($candidate)
    );
});

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

test('saveSkillsAndRightToWork persists the optional right to work and dbs expiry dates', function () {
    $application = makePendingHealthcareApplication();

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('step', 3)
        ->set('right_to_work_type', 'visa')
        ->set('right_to_work_expiry_date', '2027-01-01')
        ->set('has_dbs', 'yes')
        ->set('dbs_expiry_date', '2029-03-01')
        ->call('saveSkillsAndRightToWork')
        ->assertHasNoErrors();

    $candidate = $application->candidate->fresh();
    expect($candidate->right_to_work_expiry_date->toDateString())->toBe('2027-01-01');
    expect($candidate->dbs_expiry_date->toDateString())->toBe('2029-03-01');
});

test('saveSkillsAndRightToWork persists the right to work expiry date for a passport too', function () {
    $application = makePendingHealthcareApplication();

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('step', 3)
        ->set('right_to_work_type', 'passport')
        ->set('right_to_work_expiry_date', '2030-05-01')
        ->set('has_dbs', 'no')
        ->call('saveSkillsAndRightToWork')
        ->assertHasNoErrors();

    expect($application->candidate->fresh()->right_to_work_expiry_date->toDateString())->toBe('2030-05-01');
});

test('saveSkillsAndRightToWork clears the right to work and dbs expiry dates when not applicable', function () {
    $application = makePendingHealthcareApplication();

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('step', 3)
        ->set('right_to_work_type', 'birth_certificate')
        ->set('right_to_work_expiry_date', '2027-01-01')
        ->set('has_dbs', 'no')
        ->set('dbs_expiry_date', '2029-03-01')
        ->call('saveSkillsAndRightToWork')
        ->assertHasNoErrors();

    $candidate = $application->candidate->fresh();
    expect($candidate->right_to_work_expiry_date)->toBeNull();
    expect($candidate->dbs_expiry_date)->toBeNull();
});
