<?php

use App\Ai\Agents\CvParser;
use App\Enums\ReferenceStatus;
use App\Enums\ReferenceType;
use App\Models\CandidateSkill;
use App\Models\EducationApplication;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\Qualification;
use App\Services\ApplicationAccessSession;
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

    $application = EducationApplication::factory()->create([
        'education_candidate_id' => $candidate->id,
        'status' => 'pending',
    ]);

    ApplicationAccessSession::markVerified($application->token);

    return $application;
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

    ApplicationAccessSession::markVerified($application->token);

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

    ApplicationAccessSession::markVerified($application->token);

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
        'employmentHistory' => [
            [
                'companyName' => 'Oakwood Primary',
                'jobTitle' => 'Teacher',
                'workedFrom' => '2020-09-01',
                'workedTo' => null,
            ],
        ],
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
        ->assertSet('date_of_birth', 'May 15, 1990')
        ->assertSet('employmentHistories.0.company_name', 'Oakwood Primary')
        ->assertSet('employmentHistories.0.job_title', 'Teacher');

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
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 3);

    $candidate = $application->educationCandidate()->first();
    expect($candidate->first_name)->toBe('Jane');
    expect($candidate->last_name)->toBe('Doe');
    expect($candidate->city)->toBe('London');

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

    ApplicationAccessSession::markVerified($application->token);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertSet('currentStep', 2)
        ->assertSet('first_name', 'Priya')
        ->assertSet('last_name', 'Shah')
        ->assertSet('date_of_birth', 'Mar 2, 1985')
        ->assertSet('city', 'Leeds')
        ->assertSet('mobile', '07700900123');
});

test('saveMedicalInformation requires a yes/no answer and advances to the consent step', function () {
    $application = makePendingApplication();
    $candidate = $application->educationCandidate;

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 3)
        ->call('saveMedicalInformation')
        ->assertHasErrors(['has_health_condition_or_disability']);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 3)
        ->set('has_health_condition_or_disability', 'no')
        ->set('emergency_contact_name', 'Jane Smith')
        ->set('emergency_contact_number', '07700900000')
        ->call('saveMedicalInformation')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 4);

    $candidate->refresh();
    expect($candidate->has_health_condition_or_disability)->toBe('no');
    expect($candidate->health_condition_details)->toBeNull();
    expect($candidate->emergency_contact_name)->toBe('Jane Smith');
    expect($application->fresh()->current_step)->toBe(4);
});

test('saveMedicalInformation requires details when the candidate answers yes', function () {
    $application = makePendingApplication();
    $candidate = $application->educationCandidate;

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 3)
        ->set('has_health_condition_or_disability', 'yes')
        ->call('saveMedicalInformation')
        ->assertHasErrors(['health_condition_details']);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 3)
        ->set('has_health_condition_or_disability', 'yes')
        ->set('health_condition_details', 'Needs step-free access.')
        ->call('saveMedicalInformation')
        ->assertHasNoErrors();

    expect($candidate->refresh()->health_condition_details)->toBe('Needs step-free access.');
});

test('acceptTerms requires the consent checkbox to be checked', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 4)
        ->call('acceptTerms')
        ->assertHasErrors(['terms_accepted']);

    expect($application->fresh()->terms_accepted_at)->toBeNull();
});

test('acceptTerms records the timestamp and moves to the declaration sub-step without leaving the consent step', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 4)
        ->assertSet('consentSubStep', 1)
        ->set('terms_accepted', true)
        ->call('acceptTerms')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 4)
        ->assertSet('consentSubStep', 2);

    expect($application->fresh()->terms_accepted_at)->not->toBeNull();
});

test('acceptDeclaration requires the declaration checkbox to be checked', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 4)
        ->set('consentSubStep', 2)
        ->call('acceptDeclaration')
        ->assertHasErrors(['declaration_accepted']);

    expect($application->fresh()->declaration_accepted_at)->toBeNull();
});

test('acceptDeclaration records the timestamp and advances to the employment conduct step', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 4)
        ->set('consentSubStep', 2)
        ->set('declaration_accepted', true)
        ->call('acceptDeclaration')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 5);

    expect($application->fresh()->declaration_accepted_at)->not->toBeNull();
    expect($application->fresh()->current_step)->toBe(5);
});

test('mount resumes at the declaration sub-step when terms have already been accepted', function () {
    $application = makePendingApplication();
    $application->update(['terms_accepted_at' => now()]);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 4)
        ->assertSet('consentSubStep', 2)
        ->assertSet('terms_accepted', true);
});

test('the consent step generates a url for the kcsie pdf', function () {
    $application = makePendingApplication();

    $component = Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 4);

    $url = $component->instance()->kcsiePdfUrl();

    expect($url)->toContain('kcsie.pdf');
});

test('saveEmploymentConduct requires yes/no answers for all three questions', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 5)
        ->call('saveEmploymentConduct')
        ->assertHasErrors(['retired_early', 'dismissed_from_relevant_position', 'subject_to_disciplinary_action']);
});

test('saveEmploymentConduct requires medical grounds answer when retired early is yes', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 5)
        ->set('retired_early', 'yes')
        ->set('dismissed_from_relevant_position', 'no')
        ->set('subject_to_disciplinary_action', 'no')
        ->call('saveEmploymentConduct')
        ->assertHasErrors(['retired_early_medical_grounds']);
});

test('saveEmploymentConduct requires details when dismissed from a relevant position', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 5)
        ->set('retired_early', 'no')
        ->set('dismissed_from_relevant_position', 'yes')
        ->set('subject_to_disciplinary_action', 'no')
        ->call('saveEmploymentConduct')
        ->assertHasErrors(['dismissal_details']);
});

test('saveEmploymentConduct requires details when subject to disciplinary action', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 5)
        ->set('retired_early', 'no')
        ->set('dismissed_from_relevant_position', 'no')
        ->set('subject_to_disciplinary_action', 'yes')
        ->call('saveEmploymentConduct')
        ->assertHasErrors(['disciplinary_action_details']);
});

test('saveEmploymentConduct persists answers and advances to the photo step', function () {
    $application = makePendingApplication();
    $candidate = $application->educationCandidate;

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 5)
        ->set('retired_early', 'yes')
        ->set('retired_early_medical_grounds', 'yes')
        ->set('dismissed_from_relevant_position', 'yes')
        ->set('dismissal_details', 'Redundancy dispute.')
        ->set('subject_to_disciplinary_action', 'yes')
        ->set('disciplinary_action_details', 'Verbal warning in 2019.')
        ->call('saveEmploymentConduct')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 6);

    $candidate->refresh();
    expect($candidate->retired_early)->toBe('yes');
    expect($candidate->retired_early_medical_grounds)->toBe('yes');
    expect($candidate->dismissed_from_relevant_position)->toBe('yes');
    expect($candidate->dismissal_details)->toBe('Redundancy dispute.');
    expect($candidate->subject_to_disciplinary_action)->toBe('yes');
    expect($candidate->disciplinary_action_details)->toBe('Verbal warning in 2019.');
    expect($application->fresh()->current_step)->toBe(6);
});

test('saveEmploymentConduct clears conditional detail fields when answers are no', function () {
    $application = makePendingApplication();
    $candidate = $application->educationCandidate;

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 5)
        ->set('retired_early', 'no')
        ->set('dismissed_from_relevant_position', 'no')
        ->set('subject_to_disciplinary_action', 'no')
        ->call('saveEmploymentConduct')
        ->assertHasNoErrors();

    $candidate->refresh();
    expect($candidate->retired_early_medical_grounds)->toBeNull();
    expect($candidate->dismissal_details)->toBeNull();
    expect($candidate->disciplinary_action_details)->toBeNull();
});

test('savePhoto requires a photo when none exists yet', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 6)
        ->call('savePhoto')
        ->assertHasErrors('photo')
        ->assertSet('currentStep', 6);

    expect($application->fresh()->educationCandidate->photo_path)->toBeNull();
});

test('savePhoto advances to step 7 without re-uploading when a photo already exists', function () {
    $application = makePendingApplication();
    $application->educationCandidate->update(['photo_path' => 'existing/photo.jpg']);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 6)
        ->call('savePhoto')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 7);
});

test('form displays the existing photo when the candidate already has one', function () {
    $application = makePendingApplication();
    $application->educationCandidate->update(['photo_path' => 'existing/photo.jpg']);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 6)
        ->assertSee('Replace photo');
});

test('savePhoto validates photo is an image', function () {
    $application = makePendingApplication();

    $file = UploadedFile::fake()->create('photo.pdf', 100, 'application/pdf');

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 6)
        ->set('photo', $file)
        ->call('savePhoto')
        ->assertHasErrors(['photo' => 'image']);
});

test('savePhoto persists candidate photo and advances to step 7', function () {
    $application = makePendingApplication();

    $file = UploadedFile::fake()->image('photo.jpg');

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 6)
        ->set('photo', $file)
        ->call('savePhoto')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 7);

    $candidate = $application->educationCandidate()->first();
    expect($candidate->photo_path)->not->toBeNull();

    expect($application->fresh()->status)->toBe('pending');
    expect($application->fresh()->completed_at)->toBeNull();
    expect($application->fresh()->current_step)->toBe(7);
});

test('saveWorkPreferences persists skills, qualification, and work preferences and advances to references step', function () {
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
        ->set('currentStep', 7)
        ->set('qualification_id', $qualification->id)
        ->set('availability', ['long_term', 'part_time'])
        ->set('available_from', now()->addWeek()->toDateString())
        ->set('key_stages', ['keystage_1', 'keystage_2'])
        ->set('skills', [$childSkill->id])
        ->call('saveWorkPreferences')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 8);

    $candidate->refresh();
    expect($candidate->qualification_id)->toBe($qualification->id);
    expect($candidate->availability)->toBe(['long_term', 'part_time']);
    expect($candidate->available_from->toDateString())->toBe(now()->addWeek()->toDateString());
    expect($candidate->key_stages)->toBe(['keystage_1', 'keystage_2']);
    expect($candidate->skills->pluck('id')->sort()->values()->all())->toBe([$parentSkill->id, $childSkill->id]);

    expect($application->fresh()->status)->toBe('pending');
    expect($application->fresh()->completed_at)->toBeNull();
    expect($application->fresh()->current_step)->toBe(8);
});

test('saveWorkPreferences validates availability and key_stages values', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 7)
        ->set('availability', ['not-a-real-option'])
        ->set('key_stages', ['not-a-real-key-stage'])
        ->call('saveWorkPreferences')
        ->assertHasErrors(['availability.0', 'key_stages.0']);
});

test('saveWorkPreferences requires at least one skill', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 7)
        ->set('skills', [])
        ->call('saveWorkPreferences')
        ->assertHasErrors(['skills']);

    expect($application->fresh()->current_step)->toBe(1);
});

test('mount seeds employment history from cv parsed data when none is saved yet', function () {
    $application = EducationApplication::factory()->create([
        'education_candidate_id' => EducationCandidate::factory()->create()->id,
        'status' => 'pending',
        'current_step' => 8,
        'cv_parsed_data' => [
            'employmentHistory' => [
                [
                    'companyName' => 'Oakwood Primary',
                    'jobTitle' => 'Class Teacher',
                    'workedFrom' => '2020-09-01',
                    'workedTo' => null,
                ],
                [
                    'companyName' => 'Elmfield School',
                    'jobTitle' => 'Teaching Assistant',
                    'workedFrom' => '2018-01-15',
                    'workedTo' => '2020-08-31',
                ],
            ],
        ],
    ]);

    ApplicationAccessSession::markVerified($application->token);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertCount('employmentHistories', 2)
        ->assertSet('employmentHistories.0.company_name', 'Oakwood Primary')
        ->assertSet('employmentHistories.0.job_title', 'Class Teacher')
        ->assertSet('employmentHistories.0.worked_from', 'Sep 1, 2020')
        ->assertSet('employmentHistories.0.collapsed', false)
        ->assertSet('employmentHistories.1.company_name', 'Elmfield School');
});

test('addEmploymentHistory appends a blank job row', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 8)
        ->assertCount('employmentHistories', 1)
        ->call('addEmploymentHistory')
        ->assertCount('employmentHistories', 2);
});

test('saveEmploymentHistory validates and persists a single job, then collapses it', function () {
    $application = makePendingApplication();
    $candidate = $application->educationCandidate;

    $component = Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 8)
        ->set('employmentHistories.0', [
            'company_name' => 'Oakwood Primary',
            'job_title' => 'Class Teacher',
            'worked_from' => now()->subYears(2)->format('M j, Y'),
            'worked_to' => now()->format('M j, Y'),
        ])
        ->call('saveEmploymentHistory', 0)
        ->assertHasNoErrors();

    expect($candidate->employmentHistories()->count())->toBe(1);
    $job = $candidate->employmentHistories()->first();
    expect($job->company_name)->toBe('Oakwood Primary');
    $component->assertSet('employmentHistories.0.collapsed', true);
    $component->assertSet('employmentHistories.0.id', $job->id);
});

test('saveEmploymentHistory does not persist or collapse when validation fails', function () {
    $application = makePendingApplication();
    $candidate = $application->educationCandidate;

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 8)
        ->call('saveEmploymentHistory', 0)
        ->assertHasErrors(['employmentHistories.0.company_name', 'employmentHistories.0.job_title', 'employmentHistories.0.worked_from'])
        ->assertSet('employmentHistories.0.collapsed', false);

    expect($candidate->employmentHistories()->count())->toBe(0);
});

test('removeEmploymentHistory deletes an already-saved job from the database', function () {
    $application = makePendingApplication();
    $candidate = $application->educationCandidate;

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 8)
        ->set('employmentHistories.0', [
            'company_name' => 'Oakwood Primary',
            'job_title' => 'Class Teacher',
            'worked_from' => now()->subYears(2)->format('M j, Y'),
            'worked_to' => now()->format('M j, Y'),
        ])
        ->call('saveEmploymentHistory', 0)
        ->call('addEmploymentHistory')
        ->call('removeEmploymentHistory', 0);

    expect($candidate->employmentHistories()->count())->toBe(0);
});

test('submitEmploymentHistory requires at least one job and advances to references step', function () {
    $application = makePendingApplication();
    $candidate = $application->educationCandidate;

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 8)
        ->call('submitEmploymentHistory')
        ->assertHasErrors([
            'employmentHistories.0.company_name',
            'employmentHistories.0.job_title',
            'employmentHistories.0.worked_from',
        ]);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 8)
        ->set('employmentHistories.0', [
            'company_name' => 'Oakwood Primary',
            'job_title' => 'Class Teacher',
            'worked_from' => now()->subYears(2)->format('M j, Y'),
            'worked_to' => now()->format('M j, Y'),
        ])
        ->call('submitEmploymentHistory')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 9);

    expect($candidate->employmentHistories()->count())->toBe(1);
    expect($application->fresh()->current_step)->toBe(9);
    expect($application->fresh()->status)->toBe('pending');
});

test('addReference appends a blank reference row', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 9)
        ->assertCount('references', 1)
        ->call('addReference')
        ->assertCount('references', 2);
});

test('removeReference removes the reference at the given index', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 9)
        ->call('addReference')
        ->set('references.0.first_name', 'First')
        ->set('references.1.first_name', 'Second')
        ->call('removeReference', 0)
        ->assertCount('references', 1)
        ->assertSet('references.0.first_name', 'Second');
});

test('saveReference validates and persists a single reference, then collapses it', function () {
    $application = makePendingApplication();
    $candidate = $application->educationCandidate;

    $component = Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 9)
        ->set('references.0', [
            'type' => 'professional',
            'title' => 'Mr',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'job_title' => 'Head Teacher',
            'worked_from' => now()->subYears(2)->format('M j, Y'),
            'worked_to' => now()->format('M j, Y'),
            'email' => 'jane@example.com',
            'mobile' => '07700900000',
            'address' => '1 School Lane',
            'city' => 'London',
            'county' => 'Greater London',
            'country' => 'United Kingdom',
            'postcode' => 'SW1A 1AA',
            'consent_to_contact' => true,
        ])
        ->call('saveReference', 0)
        ->assertHasNoErrors();

    expect($candidate->references()->count())->toBe(1);
    $reference = $candidate->references()->first();
    expect($reference->first_name)->toBe('Jane');
    expect($reference->worked_from->toDateString())->toBe(now()->subYears(2)->toDateString());
    expect($reference->contact_now)->toBeFalse();
    $component->assertSet('references.0.collapsed', true);
    $component->assertSet('references.0.id', $reference->id);
});

test('a new reference defaults to contact_now being off, requiring the candidate to opt in', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 9)
        ->assertSet('references.0.contact_now', false)
        ->call('addReference')
        ->assertSet('references.1.contact_now', false);
});

test('saveReference persists contact_now as true when the candidate explicitly opts in to contact', function () {
    $application = makePendingApplication();
    $candidate = $application->educationCandidate;

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 9)
        ->set('references.0', [
            'type' => 'professional',
            'title' => 'Mr',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'job_title' => 'Head Teacher',
            'worked_from' => now()->subYears(2)->format('M j, Y'),
            'worked_to' => now()->format('M j, Y'),
            'email' => 'jane@example.com',
            'mobile' => '07700900000',
            'address' => '1 School Lane',
            'city' => 'London',
            'county' => 'Greater London',
            'country' => 'United Kingdom',
            'postcode' => 'SW1A 1AA',
            'consent_to_contact' => true,
            'contact_now' => true,
        ])
        ->call('saveReference', 0)
        ->assertHasNoErrors();

    expect($candidate->references()->first()->contact_now)->toBeTrue();
});

test('saveReference updates an already-saved reference on a second save without a database error', function () {
    $application = makePendingApplication();
    $candidate = $application->educationCandidate;

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 9)
        ->set('references.0', [
            'type' => 'professional',
            'title' => 'Mr',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'job_title' => 'Head Teacher',
            'worked_from' => now()->subYears(2)->format('M j, Y'),
            'worked_to' => now()->format('M j, Y'),
            'email' => 'jane@example.com',
            'mobile' => '07700900000',
            'address' => '1 School Lane',
            'city' => 'London',
            'county' => 'Greater London',
            'country' => 'United Kingdom',
            'postcode' => 'SW1A 1AA',
            'consent_to_contact' => true,
        ])
        ->call('saveReference', 0)
        ->call('toggleReferenceCollapsed', 0)
        ->set('references.0.worked_from', now()->subYears(3)->format('M j, Y'))
        ->call('saveReference', 0)
        ->assertHasNoErrors();

    expect($candidate->references()->count())->toBe(1);
    expect($candidate->references()->first()->worked_from->toDateString())->toBe(now()->subYears(3)->toDateString());
});

test('saveReference does not persist or collapse when validation fails', function () {
    $application = makePendingApplication();
    $candidate = $application->educationCandidate;

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 9)
        ->call('saveReference', 0)
        ->assertHasErrors(['references.0.first_name'])
        ->assertSet('references.0.collapsed', false);

    expect($candidate->references()->count())->toBe(0);
});

test('toggleReferenceCollapsed expands and collapses a reference', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 9)
        ->assertSet('references.0.collapsed', false)
        ->call('toggleReferenceCollapsed', 0)
        ->assertSet('references.0.collapsed', true)
        ->call('toggleReferenceCollapsed', 0)
        ->assertSet('references.0.collapsed', false);
});

test('removeReference deletes an already-saved reference from the database', function () {
    $application = makePendingApplication();
    $candidate = $application->educationCandidate;

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 9)
        ->set('references.0', [
            'type' => 'professional',
            'title' => 'Mr',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'job_title' => 'Head Teacher',
            'worked_from' => now()->subYears(2)->toDateString(),
            'worked_to' => now()->toDateString(),
            'email' => 'jane@example.com',
            'mobile' => '07700900000',
            'address' => '1 School Lane',
            'city' => 'London',
            'county' => 'Greater London',
            'country' => 'United Kingdom',
            'postcode' => 'SW1A 1AA',
            'consent_to_contact' => true,
        ])
        ->call('saveReference', 0)
        ->call('addReference')
        ->call('removeReference', 0);

    expect($candidate->references()->count())->toBe(0);
});

test('submitApplication validates required reference fields', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 9)
        ->call('submitApplication')
        ->assertHasErrors([
            'references.0.type',
            'references.0.first_name',
            'references.0.last_name',
            'references.0.worked_from',
            'references.0.consent_to_contact',
        ]);
});

test('submitApplication rejects references that leave a gap in the last 3 years', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 9)
        ->set('references.0', [
            'type' => 'professional',
            'title' => 'Mr',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'job_title' => 'Head Teacher',
            'worked_from' => now()->subYears(3)->toDateString(),
            'worked_to' => now()->subMonths(18)->toDateString(),
            'email' => 'jane@example.com',
            'mobile' => '07700900000',
            'address' => '1 School Lane',
            'city' => 'London',
            'county' => 'Greater London',
            'country' => 'United Kingdom',
            'postcode' => 'SW1A 1AA',
            'consent_to_contact' => true,
        ])
        ->call('submitApplication')
        ->assertHasErrors(['references']);

    expect($application->fresh()->status)->toBe('pending');
});

test('submitApplication persists references and completes the application when history is fully covered', function () {
    $application = makePendingApplication();
    $candidate = $application->educationCandidate;

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 9)
        ->set('references.0', [
            'type' => 'professional',
            'title' => 'Mr',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'job_title' => 'Head Teacher',
            'worked_from' => now()->subYears(3)->toDateString(),
            'worked_to' => now()->subYears(1)->toDateString(),
            'email' => 'jane@example.com',
            'mobile' => '07700900000',
            'address' => '1 School Lane',
            'city' => 'London',
            'county' => 'Greater London',
            'country' => 'United Kingdom',
            'postcode' => 'SW1A 1AA',
            'consent_to_contact' => true,
        ])
        ->call('addReference')
        ->set('references.1', [
            'type' => 'character',
            'title' => 'Mrs',
            'first_name' => 'Alex',
            'last_name' => 'Jones',
            'job_title' => 'Deputy Head',
            'worked_from' => now()->subYears(1)->toDateString(),
            'worked_to' => null,
            'email' => 'alex@example.com',
            'mobile' => '07700900001',
            'address' => '2 School Lane',
            'city' => 'Manchester',
            'county' => 'Greater Manchester',
            'country' => 'United Kingdom',
            'postcode' => 'M1 1AA',
            'consent_to_contact' => true,
        ])
        ->call('submitApplication')
        ->assertHasNoErrors();

    expect($candidate->references()->count())->toBe(2);

    $first = $candidate->references()->where('first_name', 'Jane')->first();
    expect($first->type)->toBe(ReferenceType::Professional);
    expect($first->consent_to_contact)->toBeTrue();
    expect($first->status)->toBe(ReferenceStatus::Pending);
    expect($first->last_contacted)->toBeNull();

    expect($application->fresh()->status)->toBe('completed');
    expect($application->fresh()->completed_at)->not->toBeNull();
    expect($application->fresh()->current_step)->toBe(9);
});

test('references step does not expose status or last contacted fields to the candidate', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 9)
        ->assertDontSee('Last Contacted')
        ->assertDontSeeHtml('wire:model="references.0.status"')
        ->assertDontSeeHtml('wire:model="references.0.last_contacted"');
});

test('references step displays how much of the last 3 years is currently covered', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 9)
        ->assertSee('0 years, 0 months')
        ->set('references.0', [
            'type' => 'professional',
            'title' => 'Mr',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'job_title' => 'Head Teacher',
            'worked_from' => now()->subYears(3)->format('M j, Y'),
            'worked_to' => now()->subYears(1)->format('M j, Y'),
            'email' => 'jane@example.com',
            'mobile' => '07700900000',
            'address' => '1 School Lane',
            'city' => 'London',
            'county' => 'Greater London',
            'country' => 'United Kingdom',
            'postcode' => 'SW1A 1AA',
            'consent_to_contact' => true,
        ])
        ->assertSee('2 years')
        ->assertSee('gap');
});

test('references step reports real covered duration when the earliest reference starts after the 3 year cutoff', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->set('currentStep', 9)
        ->set('references.0', [
            'type' => 'professional',
            'title' => 'Mr',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'job_title' => 'Head Teacher',
            'worked_from' => now()->subYears(2)->subDays(2)->format('M j, Y'),
            'worked_to' => null,
            'email' => 'jane@example.com',
            'mobile' => '07700900000',
            'address' => '1 School Lane',
            'city' => 'London',
            'county' => 'Greater London',
            'country' => 'United Kingdom',
            'postcode' => 'SW1A 1AA',
            'consent_to_contact' => true,
        ])
        ->assertSee('2 years 2 days')
        ->assertDontSee('0 years, 0 months')
        ->assertSee('gap');
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
        'current_step' => 6,
        'terms_accepted_at' => now(),
        'declaration_accepted_at' => now(),
    ]);

    ApplicationAccessSession::markVerified($application->token);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertSet('currentStep', 6)
        ->assertSet('first_name', 'Priya')
        ->assertSet('last_name', 'Shah')
        ->assertSet('city', 'Manchester')
        ->assertSee('Add Your Photo');
});

test('mount defaults to the consent step when a candidate has not completed the declaration yet', function () {
    $application = EducationApplication::factory()->create([
        'education_candidate_id' => EducationCandidate::factory()->create()->id,
        'status' => 'pending',
        'current_step' => 6,
    ]);

    ApplicationAccessSession::markVerified($application->token);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertSet('currentStep', 4);

    expect($application->fresh()->current_step)->toBe(6);
});

test('mount hydrates a saved reference\'s contact_now value', function () {
    $candidate = EducationCandidate::factory()->create();

    $reference = $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Existing',
        'last_name' => 'Referee',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
        'contact_now' => false,
    ]);

    $application = EducationApplication::factory()->create([
        'education_candidate_id' => $candidate->id,
        'status' => 'pending',
        'current_step' => 9,
    ]);

    ApplicationAccessSession::markVerified($application->token);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertSet('references.0.id', $reference->id)
        ->assertSet('references.0.contact_now', false);
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
        'availability' => ['long_term', 'part_time'],
        'available_from' => now()->addWeek()->toDateString(),
        'key_stages' => ['keystage_1', 'keystage_2'],
    ]);

    $candidate->skills()->sync([$parentSkill->id, $childSkill->id]);

    $application = EducationApplication::factory()->create([
        'education_candidate_id' => $candidate->id,
        'status' => 'pending',
        'current_step' => 7,
        'terms_accepted_at' => now(),
        'declaration_accepted_at' => now(),
    ]);

    ApplicationAccessSession::markVerified($application->token);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertSet('currentStep', 7)
        ->assertSet('qualification_id', $qualification->id)
        ->assertSet('availability', ['long_term', 'part_time'])
        ->assertSet('available_from', now()->addWeek()->format('M j, Y'))
        ->assertSet('key_stages', ['keystage_1', 'keystage_2'])
        ->assertSet('skills', fn (array $skills) => collect($skills)->sort()->values()->all() === [$parentSkill->id, $childSkill->id]);
});

test('progress bar displays the current section name and percentage', function () {
    $application = EducationApplication::factory()->create([
        'education_candidate_id' => EducationCandidate::factory()->create()->id,
        'status' => 'pending',
        'current_step' => 3,
    ]);

    ApplicationAccessSession::markVerified($application->token);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertSee('Medical Information')
        ->assertSee('Step 3 of 9')
        ->assertSee('33%');
});

test('viewStep allows navigating back to an already reached step', function () {
    $application = EducationApplication::factory()->create([
        'education_candidate_id' => EducationCandidate::factory()->create()->id,
        'status' => 'pending',
        'current_step' => 4,
    ]);

    ApplicationAccessSession::markVerified($application->token);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertSet('currentStep', 4)
        ->call('viewStep', 2)
        ->assertSet('currentStep', 2)
        ->assertSee('Your Details');

    expect($application->fresh()->current_step)->toBe(4);
});

test('progress bar disables the forward arrow until navigating back to a previously reached step', function () {
    $application = EducationApplication::factory()->create([
        'education_candidate_id' => EducationCandidate::factory()->create()->id,
        'status' => 'pending',
        'current_step' => 4,
    ]);

    ApplicationAccessSession::markVerified($application->token);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertSeeHtml('wire:click="viewStep(5)" disabled="disabled"')
        ->call('viewStep', 2)
        ->assertSeeHtml('wire:click="viewStep(3)" >')
        ->call('viewStep', 4)
        ->assertSeeHtml('wire:click="viewStep(5)" disabled="disabled"');
});

test('progress bar disables the back arrow on the first step', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertSeeHtml('wire:click="viewStep(0)" disabled="disabled"');
});

test('viewStep ignores attempts to jump ahead of the furthest reached step', function () {
    $application = EducationApplication::factory()->create([
        'education_candidate_id' => EducationCandidate::factory()->create()->id,
        'status' => 'pending',
        'current_step' => 2,
    ]);

    ApplicationAccessSession::markVerified($application->token);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertSet('currentStep', 2)
        ->call('viewStep', 4)
        ->assertSet('currentStep', 2);
});

test('navigating back then forward again does not regress the persisted furthest step', function () {
    $application = makePendingApplication();
    $application->update(['terms_accepted_at' => now(), 'declaration_accepted_at' => now()]);

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
        ->assertSet('currentStep', 7);

    expect($application->fresh()->current_step)->toBe(7);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertSet('currentStep', 7)
        ->call('viewStep', 2)
        ->assertSet('currentStep', 2)
        ->set('last_name', 'Smith')
        ->call('nextStep')
        ->assertSet('currentStep', 3);

    expect($application->fresh()->current_step)->toBe(7);
    expect($application->fresh()->educationCandidate->last_name)->toBe('Smith');
});

test('mount redirects to the verify page for a session that has not verified this application', function () {
    $candidate = EducationCandidate::factory()->create();

    $application = EducationApplication::factory()->create([
        'education_candidate_id' => $candidate->id,
        'status' => 'pending',
    ]);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertRedirect(route('application.verify', ['token' => $application->token]));
});

test('mount redirects to the verify page even when the application has been verified before, if this session has not verified it', function () {
    $candidate = EducationCandidate::factory()->create();

    $application = EducationApplication::factory()->create([
        'education_candidate_id' => $candidate->id,
        'status' => 'pending',
        'email_verified' => true,
    ]);

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertRedirect(route('application.verify', ['token' => $application->token]));
});

test('mount does not redirect once this session has verified the application', function () {
    $application = makePendingApplication();

    Livewire::test('application.application-form', ['token' => $application->token])
        ->assertSet('currentStep', 1)
        ->assertSee('Upload Your CV');
});
