<?php

use App\Ai\Agents\CvParser;
use App\Models\CandidateSkill;
use App\Models\EducationApplication;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\Qualification;
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

test('parseCv requires a file when no CV has been uploaded yet', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->call('parseCv')
        ->assertHasErrors('cv');
});

test('parseCv advances to step 2 without re-uploading when a CV already exists', function () {
    $application = makePendingApplication();
    $application->update(['cv_temp_path' => 'existing/cv.pdf']);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->call('parseCv')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 2);
});

test('form displays the existing CV filename when viewing step 1 after it has been uploaded', function () {
    $application = EducationApplication::factory()->create([
        'education_candidate_id' => EducationCandidate::factory()->create()->id,
        'status' => 'pending',
        'current_step' => 2,
        'cv_temp_path' => 'company/1/candidate/my-resume.pdf',
    ]);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->call('viewStep', 1)
        ->assertSee('CV uploaded')
        ->assertSee('my-resume.pdf')
        ->assertSee('Next');
});

test('form shows the analyse button once a new CV is staged to replace an existing one', function () {
    $application = EducationApplication::factory()->create([
        'education_candidate_id' => EducationCandidate::factory()->create()->id,
        'status' => 'pending',
        'current_step' => 2,
        'cv_temp_path' => 'company/1/candidate/my-resume.pdf',
    ]);

    $file = UploadedFile::fake()->create('new-cv.pdf', 200, 'application/pdf');

    Livewire::test('application.application-form', ['token' => $application->token])
        ->call('viewStep', 1)
        ->set('cv', $file)
        ->assertSee('Analyse CV')
        ->assertDontSee('Next');
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

    expect($application->fresh()->current_step)->toBe(2);
    expect($application->fresh()->cv_parsed_data)->not->toBeEmpty();
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
    expect($application->fresh()->current_step)->toBe(2);
});

test('nextStep validates required personal details fields', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 2)
        ->call('nextStep')
        ->assertHasErrors(['first_name', 'last_name', 'address', 'city', 'postcode']);
});

test('nextStep persists candidate data and advances to step 3', function () {
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
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 3);

    $candidate = $application->educationCandidate()->first();
    expect($candidate->first_name)->toBe('Jane');
    expect($candidate->last_name)->toBe('Doe');
    expect($candidate->city)->toBe('London');
    expect($candidate->employment_history)->toBe('Teacher at Oakwood Primary 2020–Present');

    expect($application->fresh()->status)->toBe('pending');
    expect($application->fresh()->completed_at)->toBeNull();
    expect($application->fresh()->current_step)->toBe(3);
});

test('mount hydrates step 2 fields already saved on the candidate, preferring them over cv parsed data', function () {
    $candidate = EducationCandidate::factory()->create([
        'first_name' => 'Priya',
        'last_name' => 'Shah',
        'date_of_birth' => '1985-03-02',
        'address' => '1 Real Street',
        'city' => 'Leeds',
        'postcode' => 'LS1 1AA',
    ]);

    $application = EducationApplication::factory()->create([
        'education_candidate_id' => $candidate->id,
        'status' => 'pending',
        'current_step' => 2,
        'cv_parsed_data' => [
            'firstName' => 'Wrong',
            'lastName' => 'Name',
            'dateOfBirth' => '1999-01-01',
            'mobile' => '07700900123',
        ],
    ]);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertSet('currentStep', 2)
        ->assertSet('first_name', 'Priya')
        ->assertSet('last_name', 'Shah')
        ->assertSet('date_of_birth', '1985-03-02')
        ->assertSet('city', 'Leeds')
        ->assertSet('mobile', '07700900123');
});

test('savePhoto requires a photo when none exists yet', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 3)
        ->call('savePhoto')
        ->assertHasErrors('photo')
        ->assertSet('currentStep', 3);

    expect($application->fresh()->educationCandidate->photo_path)->toBeNull();
});

test('savePhoto advances to step 4 without re-uploading when a photo already exists', function () {
    $application = makePendingApplication();
    $application->educationCandidate->update(['photo_path' => 'existing/photo.jpg']);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 3)
        ->call('savePhoto')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 4);
});

test('form displays the existing photo when the candidate already has one', function () {
    $application = makePendingApplication();
    $application->educationCandidate->update(['photo_path' => 'existing/photo.jpg']);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 3)
        ->assertSee('Replace photo');
});

test('savePhoto validates photo is an image', function () {
    $application = makePendingApplication();

    $file = UploadedFile::fake()->create('photo.pdf', 100, 'application/pdf');

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 3)
        ->set('photo', $file)
        ->call('savePhoto')
        ->assertHasErrors(['photo' => 'image']);
});

test('savePhoto persists candidate photo and advances to step 4', function () {
    $application = makePendingApplication();

    $file = UploadedFile::fake()->image('photo.jpg');

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 3)
        ->set('photo', $file)
        ->call('savePhoto')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 4);

    $candidate = $application->educationCandidate()->first();
    expect($candidate->photo_path)->not->toBeNull();

    expect($application->fresh()->status)->toBe('pending');
    expect($application->fresh()->completed_at)->toBeNull();
    expect($application->fresh()->current_step)->toBe(4);
});

test('submitApplication persists skills, qualification, and work preferences and marks application complete', function () {
    $application = makePendingApplication();
    $candidate = $application->educationCandidate;

    $qualification = Qualification::factory()->create([
        'company_id' => $candidate->company_id,
        'industry_id' => Industry::where('slug', 'education')->value('id'),
    ]);

    $parentSkill = CandidateSkill::factory()->create([
        'company_id' => $candidate->company_id,
        'industry_id' => Industry::where('slug', 'education')->value('id'),
        'name' => 'Teaching',
    ]);

    $childSkill = CandidateSkill::factory()->create([
        'company_id' => $candidate->company_id,
        'industry_id' => Industry::where('slug', 'education')->value('id'),
        'name' => 'Phonics',
        'parent_id' => $parentSkill->id,
    ]);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 4)
        ->set('qualification_id', $qualification->id)
        ->set('employment_type', 'long_term')
        ->set('available_from', now()->addWeek()->toDateString())
        ->set('key_stages', ['keystage_1', 'keystage_2'])
        ->set('skills', [$childSkill->id])
        ->call('submitApplication')
        ->assertHasNoErrors();

    $candidate->refresh();
    expect($candidate->qualification_id)->toBe($qualification->id);
    expect($candidate->employment_type)->toBe('long_term');
    expect($candidate->available_from->toDateString())->toBe(now()->addWeek()->toDateString());
    expect($candidate->key_stages)->toBe(['keystage_1', 'keystage_2']);
    expect($candidate->skills->pluck('id')->sort()->values()->all())->toBe([$parentSkill->id, $childSkill->id]);

    expect($application->fresh()->status)->toBe('completed');
    expect($application->fresh()->completed_at)->not->toBeNull();
});

test('submitApplication validates employment_type and key_stages values', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 4)
        ->set('employment_type', 'not-a-real-option')
        ->set('key_stages', ['not-a-real-key-stage'])
        ->call('submitApplication')
        ->assertHasErrors(['employment_type', 'key_stages.0']);
});

test('mount resumes at the persisted step and hydrates saved candidate data', function () {
    $candidate = EducationCandidate::factory()->create([
        'first_name' => 'Priya',
        'last_name' => 'Shah',
        'city' => 'Manchester',
    ]);

    $application = EducationApplication::factory()->create([
        'education_candidate_id' => $candidate->id,
        'status' => 'pending',
        'current_step' => 3,
    ]);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertSet('currentStep', 3)
        ->assertSet('first_name', 'Priya')
        ->assertSet('last_name', 'Shah')
        ->assertSet('city', 'Manchester')
        ->assertSee('Add Your Photo');
});

test('mount hydrates qualification, work preferences, and skills already saved on the candidate', function () {
    $candidate = EducationCandidate::factory()->create();

    $qualification = Qualification::factory()->create([
        'company_id' => $candidate->company_id,
        'industry_id' => Industry::where('slug', 'education')->value('id'),
    ]);

    $parentSkill = CandidateSkill::factory()->create([
        'company_id' => $candidate->company_id,
        'industry_id' => Industry::where('slug', 'education')->value('id'),
        'name' => 'Teaching',
    ]);

    $childSkill = CandidateSkill::factory()->create([
        'company_id' => $candidate->company_id,
        'industry_id' => Industry::where('slug', 'education')->value('id'),
        'name' => 'Phonics',
        'parent_id' => $parentSkill->id,
    ]);

    $candidate->update([
        'qualification_id' => $qualification->id,
        'employment_type' => 'long_term',
        'available_from' => now()->addWeek()->toDateString(),
        'key_stages' => ['keystage_1', 'keystage_2'],
    ]);

    $candidate->skills()->sync([$parentSkill->id, $childSkill->id]);

    $application = EducationApplication::factory()->create([
        'education_candidate_id' => $candidate->id,
        'status' => 'pending',
        'current_step' => 4,
    ]);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertSet('currentStep', 4)
        ->assertSet('qualification_id', $qualification->id)
        ->assertSet('employment_type', 'long_term')
        ->assertSet('available_from', now()->addWeek()->toDateString())
        ->assertSet('key_stages', ['keystage_1', 'keystage_2'])
        ->assertSet('skills', fn (array $skills) => collect($skills)->sort()->values()->all() === [$parentSkill->id, $childSkill->id]);
});

test('progress bar displays the current section name and percentage', function () {
    $application = EducationApplication::factory()->create([
        'education_candidate_id' => EducationCandidate::factory()->create()->id,
        'status' => 'pending',
        'current_step' => 3,
    ]);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertSee('Photo')
        ->assertSee('Step 3 of 4')
        ->assertSee('75%');
});

test('viewStep allows navigating back to an already reached step', function () {
    $application = EducationApplication::factory()->create([
        'education_candidate_id' => EducationCandidate::factory()->create()->id,
        'status' => 'pending',
        'current_step' => 4,
    ]);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertSet('currentStep', 4)
        ->call('viewStep', 2)
        ->assertSet('currentStep', 2)
        ->assertSee('Your Details');

    expect($application->fresh()->current_step)->toBe(4);
});

test('viewStep ignores attempts to jump ahead of the furthest reached step', function () {
    $application = EducationApplication::factory()->create([
        'education_candidate_id' => EducationCandidate::factory()->create()->id,
        'status' => 'pending',
        'current_step' => 2,
    ]);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertSet('currentStep', 2)
        ->call('viewStep', 4)
        ->assertSet('currentStep', 2);
});

test('navigating back then forward again does not regress the persisted furthest step', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 2)
        ->set('first_name', 'Jane')
        ->set('last_name', 'Doe')
        ->set('date_of_birth', '1990-05-15')
        ->set('address', '10 Downing Street')
        ->set('city', 'London')
        ->set('postcode', 'SW1A 2AA')
        ->call('nextStep')
        ->assertSet('currentStep', 3);

    $file = UploadedFile::fake()->image('photo.jpg');

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('photo', $file)
        ->call('savePhoto')
        ->assertSet('currentStep', 4);

    expect($application->fresh()->current_step)->toBe(4);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertSet('currentStep', 4)
        ->call('viewStep', 2)
        ->assertSet('currentStep', 2)
        ->set('last_name', 'Smith')
        ->call('nextStep')
        ->assertSet('currentStep', 3);

    expect($application->fresh()->current_step)->toBe(4);
    expect($application->fresh()->educationCandidate->last_name)->toBe('Smith');
});
