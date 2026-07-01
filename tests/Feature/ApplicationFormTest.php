<?php

use App\Ai\Agents\CvParser;
use App\Models\EducationApplication;
use App\Models\EducationCandidate;
use App\Models\Industry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    Industry::factory()->create(['name' => 'Education', 'slug' => 'education']);
});

function makePendingApplication(): EducationApplication
{
    $candidate = EducationCandidate::factory()->create();

    return EducationApplication::factory()->create([
        'education_candidate_id' => $candidate->id,
        'status' => 'pending',
    ]);
}

test('form renders step 1 for valid pending application', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertSet('currentStep', 1)
        ->assertSee('Upload Your CV');
});

test('mount aborts 404 for unknown token', function () {
    Livewire::test('application.application-form', ['token' => 'invalid-token'])
        ->assertStatus(404);
});

test('mount aborts 403 for expired application', function () {
    $application = EducationApplication::factory()->expired()->create([
        'education_candidate_id' => EducationCandidate::factory()->create(['company_id' => null])->id,
    ]);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertStatus(403);
});

test('parseCv validates that a file is required', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->call('parseCv')
        ->assertHasErrors(['cv' => 'required']);
});

test('parseCv validates pdf mime type', function () {
    $application = makePendingApplication();

    $file = UploadedFile::fake()->create('cv.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('cv', $file)
        ->call('parseCv')
        ->assertHasErrors(['cv' => 'mimes']);
});

test('parseCv populates fields and advances to step 2', function () {
    CvParser::fake(fn () => [
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'dateOfBirth' => '1990-05-15',
        'address' => '10 Downing Street',
        'city' => 'London',
        'postcode' => 'SW1A 2AA',
        'phone' => '02079460000',
        'mobile' => '07700900000',
        'employmentHistory' => 'Teacher at Oakwood Primary 2020–Present',
        'educationAndQualification' => 'BA Education',
    ]);

    $application = makePendingApplication();

    $file = UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf');

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('cv', $file)
        ->call('parseCv')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 2)
        ->assertSet('first_name', 'Jane')
        ->assertSet('last_name', 'Doe')
        ->assertSet('city', 'London')
        ->assertSet('date_of_birth', '1990-05-15')
        ->assertSet('employment_history', 'Teacher at Oakwood Primary 2020–Present');

    $cvTempPath = $application->fresh()->cv_temp_path;
    expect($cvTempPath)->not->toBeNull();
    Storage::disk('local')->assertExists($cvTempPath);
});

test('parseCv advances to step 2 with error message when parsing fails', function () {
    CvParser::fake(fn () => throw new RuntimeException('OpenAI error'));

    $application = makePendingApplication();

    $file = UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf');

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('cv', $file)
        ->call('parseCv')
        ->assertSet('currentStep', 2)
        ->assertSet('parseError', 'CV parsing failed. Please fill in your details manually below.');

    expect($application->fresh()->cv_temp_path)->not->toBeNull();
});

test('saveApplication validates required personal details fields', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 2)
        ->call('saveApplication')
        ->assertHasErrors(['first_name', 'last_name', 'address', 'city', 'postcode']);
});

test('saveApplication persists candidate data and marks application complete', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 2)
        ->set('first_name', 'Jane')
        ->set('last_name', 'Doe')
        ->set('date_of_birth', '1990-05-15')
        ->set('address', '10 Downing Street')
        ->set('city', 'London')
        ->set('postcode', 'SW1A 2AA')
        ->set('phone', '02079460000')
        ->set('mobile', '07700900000')
        ->set('employment_history', 'Teacher at Oakwood Primary 2020–Present')
        ->call('saveApplication')
        ->assertHasNoErrors();

    $candidate = $application->educationCandidate()->first();
    expect($candidate->first_name)->toBe('Jane');
    expect($candidate->last_name)->toBe('Doe');
    expect($candidate->city)->toBe('London');
    expect($candidate->employment_history)->toBe('Teacher at Oakwood Primary 2020–Present');

    expect($application->fresh()->status)->toBe('completed');
    expect($application->fresh()->completed_at)->not->toBeNull();
});
