<?php

use App\Ai\Agents\CvParser;
use App\Enums\DocumentType;
use App\Jobs\GenerateFormattedCv;
use App\Models\CandidateSkill;
use App\Models\HealthcareApplication;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\ReferenceForm;
use App\Models\User;
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

function healthcareReferenceFormFor(HealthcareApplication $application, string $name = 'Professional', bool $statementOnly = false): ReferenceForm
{
    $factory = ReferenceForm::factory();

    if ($statementOnly) {
        $factory = $factory->statementOnly();
    }

    return $factory->create([
        'company_id' => $application->candidate->company_id,
        'industry_id' => Industry::where('slug', 'healthcare')->value('id'),
        'name' => $name,
    ]);
}

test('mount aborts 404 for unknown token', function () {
    Livewire::test('application.healthcare-application-form', ['token' => 'not-a-real-token'])
        ->assertStatus(404);
});

test('mount redirects to the verify page for a session that has not verified this application', function () {
    $candidate = HealthcareCandidate::factory()->create();

    $application = HealthcareApplication::create([
        'candidate_type' => HealthcareCandidate::class,
        'candidate_id' => $candidate->id,
        'email' => $candidate->email,
        'status' => 'pending',
        'token' => Str::uuid(),
        'expires_on' => now()->addDays(7)->toDateString(),
    ]);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->assertRedirect(route('application.healthcare.verify', ['token' => $application->token]));
});

test('parseCv requires a file when no CV has been uploaded yet', function () {
    $application = makePendingHealthcareApplication();

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->call('parseCv')
        ->assertHasErrors(['cv']);
});

test('parseCv rejects unsupported file types', function () {
    $application = makePendingHealthcareApplication();

    $file = UploadedFile::fake()->create('cv.txt', 100, 'text/plain');

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('cv', $file)
        ->call('parseCv')
        ->assertHasErrors(['cv' => 'mimes']);
});

test('parseCv populates fields and advances to step 2', function () {
    CvParser::fake(fn () => [
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'address' => '10 Downing Street',
        'city' => 'London',
        'postcode' => 'SW1A 2AA',
        'phone' => '02079460000',
        'mobile' => '07700900000',
        'employmentHistory' => [
            [
                'companyName' => 'Oakwood Care Home',
                'jobTitle' => 'Healthcare Assistant',
                'workedFrom' => '2020-09-01',
                'workedTo' => null,
            ],
        ],
    ]);

    $application = makePendingHealthcareApplication();

    $file = UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf');

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('cv', $file)
        ->call('parseCv')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 2)
        ->assertSet('first_name', 'Jane')
        ->assertSet('last_name', 'Doe')
        ->assertSet('city', 'London')
        ->assertSet('employmentHistories.0.company_name', 'Oakwood Care Home')
        ->assertSet('employmentHistories.0.job_title', 'Healthcare Assistant');

    $cvPath = $application->fresh()->candidate->documents()->where('document_type', DocumentType::Cv)->value('path');
    expect($cvPath)->not->toBeNull();
    Storage::disk('local')->assertExists($cvPath);

    expect($application->fresh()->current_step)->toBe(2);
    expect($application->fresh()->cv_parsed_data)->not->toBeEmpty();
});

test('parseCv dispatches formatted CV generation once the CV is uploaded', function () {
    CvParser::fake(fn () => ['firstName' => 'Jane', 'lastName' => 'Doe']);

    $application = makePendingHealthcareApplication();
    $file = UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf');

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('cv', $file)
        ->call('parseCv')
        ->assertHasNoErrors();

    Bus::assertDispatched(
        GenerateFormattedCv::class,
        fn (GenerateFormattedCv $job): bool => $job->candidate->is($application->fresh()->candidate)
    );
});

test('parseCv advances to step 2 with an error message when parsing fails', function () {
    CvParser::fake(fn () => throw new RuntimeException('OpenAI error'));

    $application = makePendingHealthcareApplication();
    $file = UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf');

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('cv', $file)
        ->call('parseCv')
        ->assertSet('currentStep', 2)
        ->assertSet('parseError', 'CV parsing failed. Please fill in your details manually below.');

    expect($application->fresh()->current_step)->toBe(2);
});

test('parseCv advances to step 2 without re-uploading when a CV already exists', function () {
    $application = makePendingHealthcareApplication();
    $application->update(['current_step' => 1]);
    $application->candidate->documents()->create(['document_type' => DocumentType::Cv, 'path' => 'cvs/existing.pdf']);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->call('parseCv')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 2);
});

test('savePersonalDetails accepts a valid dropdown title', function () {
    $application = makePendingHealthcareApplication();
    $application->update(['current_step' => 2]);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('title', 'Dr')
        ->set('first_name', 'Jane')
        ->set('last_name', 'Doe')
        ->call('savePersonalDetails')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 3);

    expect($application->candidate->fresh()->title)->toBe('Dr');
});

test('savePersonalDetails rejects a title that is not one of the dropdown options', function () {
    $application = makePendingHealthcareApplication();
    $application->update(['current_step' => 2]);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('title', 'Sir')
        ->set('first_name', 'Jane')
        ->set('last_name', 'Doe')
        ->call('savePersonalDetails')
        ->assertHasErrors(['title']);
});

test('savePersonalDetails allows title to be left blank', function () {
    $application = makePendingHealthcareApplication();
    $application->update(['current_step' => 2]);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('title', null)
        ->set('first_name', 'Jane')
        ->set('last_name', 'Doe')
        ->call('savePersonalDetails')
        ->assertHasNoErrors();
});

test('saveSkillsAndRightToWork persists skills, syncing parent skills automatically', function () {
    $application = makePendingHealthcareApplication();
    $application->update(['current_step' => 3]);
    $candidate = $application->candidate;

    $parentSkill = CandidateSkill::factory()->create([
        'company_id' => $candidate->company_id,
        'industry_id' => Industry::where('slug', 'healthcare')->value('id'),
        'name' => 'Personal Care',
    ]);

    $childSkill = CandidateSkill::factory()->create([
        'company_id' => $candidate->company_id,
        'industry_id' => Industry::where('slug', 'healthcare')->value('id'),
        'name' => 'Medication Administration',
        'parent_id' => $parentSkill->id,
    ]);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('skills', [$childSkill->id])
        ->set('right_to_work_type', 'visa')
        ->set('right_to_work_expiry_date', '2027-01-01')
        ->set('has_dbs', 'yes')
        ->set('dbs_expiry_date', '2029-03-01')
        ->call('saveSkillsAndRightToWork')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 4);

    $candidate->refresh();
    expect($candidate->skills->pluck('id')->sort()->values()->all())->toBe([$parentSkill->id, $childSkill->id]);
    expect($candidate->right_to_work_expiry_date->toDateString())->toBe('2027-01-01');
    expect($candidate->dbs_expiry_date->toDateString())->toBe('2029-03-01');
});

test('saveSkillsAndRightToWork requires at least one skill', function () {
    $application = makePendingHealthcareApplication();
    $application->update(['current_step' => 3]);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('skills', [])
        ->set('right_to_work_type', 'birth_certificate')
        ->set('has_dbs', 'no')
        ->call('saveSkillsAndRightToWork')
        ->assertHasErrors(['skills']);
});

test('saveSkillsAndRightToWork clears the right to work and dbs expiry dates when not applicable', function () {
    $application = makePendingHealthcareApplication();
    $application->update(['current_step' => 3]);
    $candidate = $application->candidate;

    $skill = CandidateSkill::factory()->create([
        'company_id' => $candidate->company_id,
        'industry_id' => Industry::where('slug', 'healthcare')->value('id'),
    ]);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('skills', [$skill->id])
        ->set('right_to_work_type', 'birth_certificate')
        ->set('right_to_work_expiry_date', '2027-01-01')
        ->set('has_dbs', 'no')
        ->set('dbs_expiry_date', '2029-03-01')
        ->call('saveSkillsAndRightToWork')
        ->assertHasNoErrors();

    $candidate->refresh();
    expect($candidate->right_to_work_expiry_date)->toBeNull();
    expect($candidate->dbs_expiry_date)->toBeNull();
});

test('mount seeds employment history from cv parsed data when none is saved yet', function () {
    $application = makePendingHealthcareApplication();
    $application->update([
        'current_step' => 2,
        'cv_parsed_data' => [
            'employmentHistory' => [
                ['companyName' => 'Riverside Home', 'jobTitle' => 'Support Worker', 'workedFrom' => '2019-01-01', 'workedTo' => '2021-06-01'],
            ],
        ],
    ]);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->assertSet('employmentHistories.0.company_name', 'Riverside Home')
        ->assertSet('employmentHistories.0.job_title', 'Support Worker');
});

test('addEmploymentHistory appends a blank job row', function () {
    $application = makePendingHealthcareApplication();
    $application->update(['current_step' => 4]);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->call('addEmploymentHistory')
        ->assertCount('employmentHistories', 2);
});

test('saveEmploymentHistory validates and persists a single job, then collapses it', function () {
    $application = makePendingHealthcareApplication();
    $application->update(['current_step' => 4]);
    $candidate = $application->candidate;

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('employmentHistories.0', [
            'id' => null,
            'company_name' => 'Oakwood Care Home',
            'job_title' => 'Healthcare Assistant',
            'worked_from' => now()->subYear()->toDateString(),
            'worked_to' => null,
            'collapsed' => false,
        ])
        ->call('saveEmploymentHistory', 0)
        ->assertHasNoErrors()
        ->assertSet('employmentHistories.0.collapsed', true);

    expect($candidate->employmentHistories()->where('company_name', 'Oakwood Care Home')->exists())->toBeTrue();
});

test('removeEmploymentHistory deletes an already-saved job from the database', function () {
    $application = makePendingHealthcareApplication();
    $application->update(['current_step' => 4]);
    $candidate = $application->candidate;

    $job = $candidate->employmentHistories()->create([
        'company_name' => 'Oakwood Care Home',
        'job_title' => 'Healthcare Assistant',
        'worked_from' => now()->subYear(),
    ]);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('employmentHistories.0', [
            'id' => $job->id,
            'company_name' => $job->company_name,
            'job_title' => $job->job_title,
            'worked_from' => $job->worked_from->toDateString(),
            'worked_to' => null,
            'collapsed' => true,
        ])
        ->call('removeEmploymentHistory', 0);

    expect($candidate->employmentHistories()->whereKey($job->id)->exists())->toBeFalse();
});

test('submitEmploymentHistory requires at least one job and advances to the references step', function () {
    $application = makePendingHealthcareApplication();
    $application->update(['current_step' => 4]);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('employmentHistories.0', [
            'id' => null,
            'company_name' => 'Oakwood Care Home',
            'job_title' => 'Healthcare Assistant',
            'worked_from' => now()->subYear()->toDateString(),
            'worked_to' => null,
            'collapsed' => false,
        ])
        ->call('submitEmploymentHistory')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 5);

    expect($application->fresh()->current_step)->toBe(5);
});

test('a new reference defaults to contact_now being off, requiring the candidate to opt in', function () {
    $application = makePendingHealthcareApplication();
    $application->update(['current_step' => 5]);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->assertSet('references.0.contact_now', false)
        ->call('addReference')
        ->assertSet('references.1.contact_now', false);
});

test('saveReference persists contact_now as true only when the candidate explicitly opts in', function () {
    $application = makePendingHealthcareApplication();
    $candidate = $application->candidate;
    $application->update(['current_step' => 5]);
    $professionalForm = healthcareReferenceFormFor($application);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('references.0', [
            'reference_form_id' => $professionalForm->id,
            'title' => 'Mr',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'job_title' => 'Ward Manager',
            'worked_from' => now()->subYears(2)->toDateString(),
            'worked_to' => now()->toDateString(),
            'email' => 'jane@example.com',
            'mobile' => '07700900000',
            'address' => '1 Care Lane',
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

test('saveReference persists a gap/statement entry with just dates and a statement, requiring no name or consent', function () {
    $application = makePendingHealthcareApplication();
    $candidate = $application->candidate;
    $application->update(['current_step' => 5]);
    $gapForm = healthcareReferenceFormFor($application, 'Gap / Statement', statementOnly: true);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('references.0', [
            'reference_form_id' => $gapForm->id,
            'title' => null,
            'first_name' => '',
            'last_name' => '',
            'job_title' => '',
            'worked_from' => now()->subMonths(6)->toDateString(),
            'worked_to' => now()->subMonths(3)->toDateString(),
            'email' => '',
            'mobile' => '',
            'address' => '',
            'city' => '',
            'county' => '',
            'country' => '',
            'postcode' => '',
            'consent_to_contact' => false,
            'contact_now' => false,
            'statement' => 'Travelling for 3 months.',
        ])
        ->call('saveReference', 0)
        ->assertHasNoErrors();

    $reference = $candidate->references()->first();
    expect($reference->statement)->toBe('Travelling for 3 months.');
    expect($reference->first_name)->toBeNull();
    expect($reference->consent_to_contact)->toBeFalse();
});

test('submitReferences rejects references that leave a gap in the last 5 years', function () {
    $application = makePendingHealthcareApplication();
    $application->update(['current_step' => 5]);
    $professionalForm = healthcareReferenceFormFor($application);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('references.0', [
            'reference_form_id' => $professionalForm->id,
            'title' => 'Mr',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'job_title' => 'Ward Manager',
            'worked_from' => now()->subYears(5)->toDateString(),
            'worked_to' => now()->subYears(3)->toDateString(),
            'email' => 'jane@example.com',
            'mobile' => '07700900000',
            'address' => '1 Care Lane',
            'city' => 'London',
            'county' => 'Greater London',
            'country' => 'United Kingdom',
            'postcode' => 'SW1A 1AA',
            'consent_to_contact' => true,
        ])
        ->call('submitReferences')
        ->assertHasErrors(['references']);

    expect($application->fresh()->status)->toBe('pending');
});

test('submitReferences persists references and advances to the account step when history is fully covered', function () {
    $application = makePendingHealthcareApplication();
    $candidate = $application->candidate;
    $application->update(['current_step' => 5]);
    $professionalForm = healthcareReferenceFormFor($application, 'Professional');

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('references.0', [
            'reference_form_id' => $professionalForm->id,
            'title' => 'Mr',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'job_title' => 'Ward Manager',
            'worked_from' => now()->subYears(5)->toDateString(),
            'worked_to' => null,
            'email' => 'jane@example.com',
            'mobile' => '07700900000',
            'address' => '1 Care Lane',
            'city' => 'London',
            'county' => 'Greater London',
            'country' => 'United Kingdom',
            'postcode' => 'SW1A 1AA',
            'consent_to_contact' => true,
        ])
        ->call('submitReferences')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 6);

    expect($candidate->references()->count())->toBe(1);
    expect($application->fresh()->current_step)->toBe(6);
});

test('submitReferences accepts references covering at least 70% of the last 5 years even with a gap', function () {
    $application = makePendingHealthcareApplication();
    $application->update(['current_step' => 5]);
    $professionalForm = healthcareReferenceFormFor($application);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('references.0', [
            'reference_form_id' => $professionalForm->id,
            'title' => 'Mr',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'job_title' => 'Ward Manager',
            // Covers the most recent 44 of the last 60 months (~73%),
            // leaving a 16-month gap at the start of the window — below
            // 100% but above the 70% threshold.
            'worked_from' => now()->subMonths(44)->toDateString(),
            'worked_to' => null,
            'email' => 'jane@example.com',
            'mobile' => '07700900000',
            'address' => '1 Care Lane',
            'city' => 'London',
            'county' => 'Greater London',
            'country' => 'United Kingdom',
            'postcode' => 'SW1A 1AA',
            'consent_to_contact' => true,
        ])
        ->call('submitReferences')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 6);
});

test('submitReferences reports the largest gap, not just any gap, when coverage falls below 70%', function () {
    $application = makePendingHealthcareApplication();
    $application->update(['current_step' => 5]);
    $professionalForm = healthcareReferenceFormFor($application, 'Professional');
    $characterForm = healthcareReferenceFormFor($application, 'Character');

    $smallGapFrom = now()->subYears(5)->addMonths(3)->addDay();
    $smallGapTo = now()->subYears(5)->addMonths(4)->subDay();
    $largeGapFrom = now()->subYears(2)->addDay();
    $largeGapTo = today();

    $component = Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('references.0', [
            'reference_form_id' => $professionalForm->id,
            'title' => 'Mr',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'job_title' => 'Ward Manager',
            'worked_from' => now()->subYears(5)->toDateString(),
            'worked_to' => now()->subYears(5)->addMonths(3)->toDateString(),
            'email' => 'jane@example.com',
            'mobile' => '07700900000',
            'address' => '1 Care Lane',
            'city' => 'London',
            'county' => 'Greater London',
            'country' => 'United Kingdom',
            'postcode' => 'SW1A 1AA',
            'consent_to_contact' => true,
        ])
        ->call('addReference')
        ->set('references.1', [
            'reference_form_id' => $characterForm->id,
            'title' => 'Mrs',
            'first_name' => 'Alex',
            'last_name' => 'Jones',
            'job_title' => 'Deputy Ward Manager',
            'worked_from' => now()->subYears(5)->addMonths(4)->toDateString(),
            'worked_to' => now()->subYears(2)->toDateString(),
            'email' => 'alex@example.com',
            'mobile' => '07700900001',
            'address' => '2 Care Lane',
            'city' => 'Manchester',
            'county' => 'Greater Manchester',
            'country' => 'United Kingdom',
            'postcode' => 'M1 1AA',
            'consent_to_contact' => true,
        ])
        ->call('submitReferences')
        ->assertHasErrors(['references']);

    $message = collect($component->instance()->getErrorBag()->get('references'))->implode(' ');

    expect($message)
        ->toContain($largeGapFrom->format('M j, Y'))
        ->toContain($largeGapTo->format('M j, Y'))
        ->not->toContain($smallGapFrom->format('M j, Y'));
});

test('removeReference deletes an already-saved reference from the database', function () {
    $application = makePendingHealthcareApplication();
    $candidate = $application->candidate;
    $application->update(['current_step' => 5]);
    $professionalForm = healthcareReferenceFormFor($application);

    $reference = $candidate->references()->create([
        'reference_form_id' => $professionalForm->id,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'worked_from' => now()->subYear(),
        'consent_to_contact' => true,
    ]);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('references.0', [
            'id' => $reference->id,
            'reference_form_id' => $professionalForm->id,
            'title' => null,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'job_title' => '',
            'worked_from' => $reference->worked_from->toDateString(),
            'worked_to' => null,
            'email' => '',
            'mobile' => '',
            'address' => '',
            'city' => '',
            'county' => '',
            'country' => '',
            'postcode' => '',
            'consent_to_contact' => true,
            'contact_now' => false,
            'statement' => '',
            'collapsed' => true,
        ])
        ->call('removeReference', 0);

    expect($candidate->references()->whereKey($reference->id)->exists())->toBeFalse();
});

test('progress bar displays the current section name and percentage', function () {
    $application = makePendingHealthcareApplication();
    $application->update(['current_step' => 3]);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->assertSee('Right to Work & Skills')
        ->assertSee('Step 3 of 6')
        ->assertSee('50%');
});

test('viewStep allows navigating back to an already reached step', function () {
    $application = makePendingHealthcareApplication();
    $application->update(['current_step' => 4]);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->assertSet('currentStep', 4)
        ->call('viewStep', 2)
        ->assertSet('currentStep', 2)
        ->assertSee('Your Details');

    expect($application->fresh()->current_step)->toBe(4);
});

test('viewStep ignores attempts to jump ahead of the furthest reached step', function () {
    $application = makePendingHealthcareApplication();
    $application->update(['current_step' => 2]);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->assertSet('currentStep', 2)
        ->call('viewStep', 4)
        ->assertSet('currentStep', 2);
});

test('completeApplication requires a password with a matching confirmation', function () {
    $application = makePendingHealthcareApplication();
    $application->update(['current_step' => 6]);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('password', 'password')
        ->set('password_confirmation', 'different')
        ->call('completeApplication')
        ->assertHasErrors(['password']);
});

test('completeApplication creates a user linked to the candidate and completes the application', function () {
    $application = makePendingHealthcareApplication();
    $candidate = $application->candidate;
    $application->update(['current_step' => 6]);

    Livewire::test('application.healthcare-application-form', ['token' => $application->token])
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->call('completeApplication')
        ->assertHasNoErrors()
        ->assertRedirect('/candidate');

    $application->refresh();
    expect($application->status)->toBe('completed');
    expect($application->current_step)->toBe(6);
    expect($application->completed_at)->not->toBeNull();

    $user = User::where('email', $candidate->email)->first();
    expect($user)->not->toBeNull();
    expect($user->candidate_id)->toBe($candidate->id);
    expect($user->hasRole('candidate'))->toBeTrue();
});
