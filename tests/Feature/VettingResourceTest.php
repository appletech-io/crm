<?php

use App\Ai\Agents\ProofOfAddressParser;
use App\Ai\Agents\ProofOfNiParser;
use App\Enums\DocumentType;
use App\Filament\Resources\Vetting\Pages\ListVetting;
use App\Filament\Resources\Vetting\Pages\VettingWizard;
use App\Models\CandidateCandidateStatus;
use App\Models\CandidateDocument;
use App\Models\CandidateSkill;
use App\Models\CandidateStatus;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\Qualification;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

function assignStatus(EducationCandidate $candidate, Industry $industry, string $companyId, string $statusName): void
{
    $status = CandidateStatus::factory()->create([
        'company_id' => $companyId,
        'industry_id' => $industry->id,
        'name' => $statusName,
    ]);

    CandidateCandidateStatus::create([
        'model_type' => EducationCandidate::class,
        'model_id' => $candidate->id,
        'candidate_status_id' => $status->id,
    ]);
}

test('list only shows candidates whose current status is Vetting', function () {
    $vettingCandidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignStatus($vettingCandidate, $this->industry, $this->user->company_id, 'Vetting');

    $onboardingCandidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignStatus($onboardingCandidate, $this->industry, $this->user->company_id, 'Onboarding');

    Livewire::test(ListVetting::class)
        ->assertCanSeeTableRecords([$vettingCandidate])
        ->assertCanNotSeeTableRecords([$onboardingCandidate]);
});

test('vetting wizard renders and saves personal details', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'place_of_birth' => null,
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->fillForm(['place_of_birth' => 'London', 'barred_list_check' => 'yes'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($candidate->refresh()->place_of_birth)->toBe('London');
});

test('vetting wizard can update every field shown on the personal details step', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $data = [
        'title' => 'Dr',
        'first_name' => 'Jane',
        'middle_name' => 'Elizabeth',
        'last_name' => 'Smith',
        'previous_surname' => 'Jones',
        'ni_number' => 'qq123456c',
        'date_of_birth' => '1990-05-04',
        'place_of_birth' => 'Manchester',
        'email' => 'jane.smith@example.com',
        'phone' => '07123456789',
        'address' => '1 Test Street',
        'postcode' => 'M1 1AA',
        'city' => 'Manchester',
        'county' => 'Greater Manchester',
        'country' => 'United Kingdom',
        'barred_list_check' => 'yes',
    ];

    Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->fillForm($data)
        ->call('save')
        ->assertHasNoFormErrors();

    $candidate->refresh();

    expect($candidate->title)->toBe('Dr');
    expect($candidate->first_name)->toBe('Jane');
    expect($candidate->middle_name)->toBe('Elizabeth');
    expect($candidate->last_name)->toBe('Smith');
    expect($candidate->previous_surname)->toBe('Jones');
    expect($candidate->ni_number)->toBe('QQ123456C');
    expect($candidate->date_of_birth->toDateString())->toBe('1990-05-04');
    expect($candidate->place_of_birth)->toBe('Manchester');
    expect($candidate->email)->toBe('jane.smith@example.com');
    expect($candidate->phone)->toBe('07123456789');
    expect($candidate->address)->toBe('1 Test Street');
    expect($candidate->postcode)->toBe('M1 1AA');
    expect($candidate->city)->toBe('Manchester');
    expect($candidate->county)->toBe('Greater Manchester');
    expect($candidate->country)->toBe('United Kingdom');
});

test('vetting wizard rejects an invalid phone number and email', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'phone' => 'not-a-phone-number',
            'email' => 'not-an-email',
        ])
        ->call('save')
        ->assertHasFormErrors(['phone', 'email']);
});

test('vetting wizard completion stamps compliance_completed_at and compliance_completed_by', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    expect($candidate->compliance_completed_at)->toBeNull();

    Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->fillForm(['barred_list_check' => 'yes'])
        ->call('save')
        ->assertHasNoFormErrors();

    $candidate->refresh();

    expect($candidate->compliance_completed_at)->not->toBeNull();
    expect($candidate->compliance_completed_by)->toBe($this->user->id);
});

test('vetting wizard resumes on the stored compliance step', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'compliance_step' => 3,
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $wizard = Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->instance();

    expect($wizard->getStartStep())->toBe(3);
});

test('completing a wizard step advances the stored compliance step', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $test = Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()]);

    preg_match("/key: '([^']+wizard)'/", $test->html(), $matches);
    $wizardKey = $matches[1];

    $test->call('callSchemaComponentMethod', $wizardKey, 'nextStep', ['currentStepIndex' => 0]);

    expect($candidate->refresh()->compliance_step)->toBe(2);
});

test('vetting wizard can save qualification, skills and key stages', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $qualification = Qualification::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);
    $skill = CandidateSkill::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'qualification_id' => $qualification->id,
            'skills' => [$skill->id],
            'key_stages' => ['keystage_1'],
            'barred_list_check' => 'yes',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $candidate->refresh();

    expect($candidate->qualification_id)->toBe($qualification->id);
    expect($candidate->skills->pluck('id')->all())->toBe([$skill->id]);
    expect($candidate->key_stages)->toBe(['keystage_1']);
});

test('clicking next on the skills step persists qualification and skills immediately', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $qualification = Qualification::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);
    $skill = CandidateSkill::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $test = Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'qualification_id' => $qualification->id,
            'skills' => [$skill->id],
            'key_stages' => ['keystage_2'],
        ]);

    preg_match("/key: '([^']+wizard)'/", $test->html(), $matches);
    $wizardKey = $matches[1];

    $test->call('callSchemaComponentMethod', $wizardKey, 'nextStep', ['currentStepIndex' => 1]);

    $candidate->refresh();

    expect($candidate->qualification_id)->toBe($qualification->id);
    expect($candidate->skills->pluck('id')->all())->toBe([$skill->id]);
    expect($candidate->key_stages)->toBe(['keystage_2']);
    expect($candidate->compliance_step)->toBe(3);
});

test('clicking next persists the personal details step data immediately, before the final save', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'place_of_birth' => null,
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $test = Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->fillForm(['place_of_birth' => 'Leeds']);

    preg_match("/key: '([^']+wizard)'/", $test->html(), $matches);
    $wizardKey = $matches[1];

    $test->call('callSchemaComponentMethod', $wizardKey, 'nextStep', ['currentStepIndex' => 0]);

    expect($candidate->refresh()->place_of_birth)->toBe('Leeds');
});

test('the documents step shows required documents for the candidate', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'right_to_work_type' => 'birth_certificate',
        'has_dbs' => 'yes',
        'has_naric' => 'no',
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $html = Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->html();

    expect($html)->toContain('Birth Certificate');
    expect($html)->not->toContain('UK NARIC');
});

test('overseas police clearance section only shows when the candidate lived overseas', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'lived_overseas_six_months' => 'no',
        'right_to_work_type' => 'birth_certificate',
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $html = Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->html();

    // The Confirm step's checklist always shows an "Overseas Police Clearance" row,
    // so only the Security Checks step's own section (a second occurrence) is conditional.
    expect(substr_count($html, 'Overseas Police Clearance'))->toBe(1);
    expect($html)->not->toContain('Visa expiry date');

    $candidate->update([
        'lived_overseas_six_months' => 'yes',
        'right_to_work_type' => 'visa',
    ]);

    $html = Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->html();

    expect(substr_count($html, 'Overseas Police Clearance'))->toBe(2);
    expect($html)->toContain('Visa expiry date');
});

test('vetting wizard can save security checks', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'lived_overseas_six_months' => 'yes',
        'right_to_work_type' => 'visa',
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'barred_list_check' => 'yes',
            'barred_list_check_date' => '2026-01-05',
            'overseas_police_clearance_check' => 'yes',
            'overseas_police_clearance_check_date' => '2026-01-06',
            'visa_issue_date' => '2025-01-01',
            'visa_expiry_date' => '2027-01-01',
            'visa_notes' => 'Skilled worker visa, sponsor confirmed.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $candidate->refresh();

    expect($candidate->barred_list_check)->toBe('yes');
    expect($candidate->barred_list_check_date->toDateString())->toBe('2026-01-05');
    expect($candidate->overseas_police_clearance_check)->toBe('yes');
    expect($candidate->overseas_police_clearance_check_date->toDateString())->toBe('2026-01-06');
    expect($candidate->visa_issue_date->toDateString())->toBe('2025-01-01');
    expect($candidate->visa_expiry_date->toDateString())->toBe('2027-01-01');
    expect($candidate->visa_notes)->toBe('Skilled worker visa, sponsor confirmed.');
});

test('dnuCandidate is true when the current status is DNU', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'DNU');

    expect($candidate->dnuCandidate())->toBeTrue();
});

test('dnuCandidate is true when the barred list check is not cleared', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'barred_list_check' => 'no',
    ]);

    expect($candidate->dnuCandidate())->toBeTrue();
});

test('dnuCandidate is true when overseas police clearance is not cleared and applicable', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'barred_list_check' => 'yes',
        'lived_overseas_six_months' => 'yes',
        'overseas_police_clearance_check' => 'no',
    ]);

    expect($candidate->dnuCandidate())->toBeTrue();
});

test('dnuCandidate ignores overseas police clearance when the candidate never lived overseas', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'barred_list_check' => 'yes',
        'lived_overseas_six_months' => 'no',
        'overseas_police_clearance_check' => 'no',
    ]);

    expect($candidate->dnuCandidate())->toBeFalse();
});

test('dnuCandidate is false when both checks are cleared', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'barred_list_check' => 'yes',
        'lived_overseas_six_months' => 'yes',
        'overseas_police_clearance_check' => 'yes',
    ]);

    expect($candidate->dnuCandidate())->toBeFalse();
});

test('failing the barred list check blocks progression past security checks and does not advance the stored step', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $test = Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->fillForm(['barred_list_check' => 'no']);

    preg_match("/key: '([^']+wizard)'/", $test->html(), $matches);
    $wizardKey = $matches[1];

    $test->call('callSchemaComponentMethod', $wizardKey, 'nextStep', ['currentStepIndex' => 3]);

    $candidate->refresh();

    expect($candidate->barred_list_check)->toBe('no');
    expect($candidate->compliance_step)->not->toBe(5);
    expect($candidate->dnuCandidate())->toBeTrue();
});

test('clearing both security checks allows progression to the next step', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'lived_overseas_six_months' => 'yes',
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $test = Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'barred_list_check' => 'yes',
            'overseas_police_clearance_check' => 'yes',
        ]);

    preg_match("/key: '([^']+wizard)'/", $test->html(), $matches);
    $wizardKey = $matches[1];

    $test->call('callSchemaComponentMethod', $wizardKey, 'nextStep', ['currentStepIndex' => 3]);

    expect($candidate->refresh()->compliance_step)->toBe(5);
});

test('tra checks step requires trn issue date when the candidate has a TRN', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'trn_number' => '1234567',
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $test = Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()]);

    preg_match("/key: '([^']+wizard)'/", $test->html(), $matches);
    $wizardKey = $matches[1];

    $test->call('callSchemaComponentMethod', $wizardKey, 'nextStep', ['currentStepIndex' => 4]);

    expect($candidate->refresh()->compliance_step)->not->toBe(6);
});

test('tra checks step advances once trn issue date is set', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'trn_number' => '1234567',
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $test = Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->fillForm(['trn_issue_date' => '2026-01-10']);

    preg_match("/key: '([^']+wizard)'/", $test->html(), $matches);
    $wizardKey = $matches[1];

    $test->call('callSchemaComponentMethod', $wizardKey, 'nextStep', ['currentStepIndex' => 4]);

    $candidate->refresh();
    expect($candidate->compliance_step)->toBe(6);
    expect($candidate->trn_issue_date->toDateString())->toBe('2026-01-10');
});

test('tra checks step does not require trn issue date when the candidate has no TRN', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'trn_number' => null,
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $test = Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()]);

    preg_match("/key: '([^']+wizard)'/", $test->html(), $matches);
    $wizardKey = $matches[1];

    $test->call('callSchemaComponentMethod', $wizardKey, 'nextStep', ['currentStepIndex' => 4]);

    expect($candidate->refresh()->compliance_step)->toBe(6);
});

test('vetting wizard can save safeguarding and prevent training checks', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'trn_number' => null,
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'barred_list_check' => 'yes',
            'safeguarding_certified_date' => '2026-02-01',
            'prevent_training_completed' => 'yes',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $candidate->refresh();

    expect($candidate->safeguarding_certified_date->toDateString())->toBe('2026-02-01');
    expect($candidate->prevent_training_completed)->toBe('yes');
});

test('dbs step shows the new dbs section when the candidate has no certificate number', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'dbs_certificate_number' => null,
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $html = Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->html();

    expect($html)->toContain('New DBS');
    expect($html)->not->toContain('DBS Update Service');
});

test('dbs step shows the update service section when the candidate has a certificate number', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'dbs_certificate_number' => '001234567890',
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $html = Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->html();

    expect($html)->toContain('DBS Update Service');
    expect($html)->not->toContain('New DBS');
});

test('vetting wizard can save a new dbs certificate number and checked date', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'trn_number' => null,
        'dbs_certificate_number' => null,
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'barred_list_check' => 'yes',
            'dbs_certificate_number' => '001234567890',
            'dbs_checked_date' => '2026-03-01',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $candidate->refresh();

    expect($candidate->dbs_certificate_number)->toBe('001234567890');
    expect($candidate->dbs_checked_date->toDateString())->toBe('2026-03-01');
});

test('calling the update service action stores the result on the candidate', function () {
    Company::find($this->user->company_id)->update(['legal_name' => 'Applebough Ltd']);

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Billy',
        'last_name' => 'Jones',
        'date_of_birth' => '1990-05-15',
        'dbs_certificate_number' => '001234567890',
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    Http::fake([
        'secure.crbonline.gov.uk/*' => Http::response(<<<'XML'
            <statusCheckResult>
                <statusCheckResultType>SUCCESS</statusCheckResultType>
                <status>BLANK_NO_NEW_INFO</status>
            </statusCheckResult>
            XML),
    ]);

    Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->callAction(TestAction::make('callUpdateService')->schemaComponent());

    expect($candidate->refresh()->update_service_response)->toBe('BLANK_NO_NEW_INFO');

    Http::assertSent(fn ($request) => $request['employeeForename'] === 'Billy' && $request['employeeSurname'] === 'Jones');
});

test('calling the update service action shows an error when the check fails', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'dbs_certificate_number' => '001234567890',
        'date_of_birth' => null,
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->callAction(TestAction::make('callUpdateService')->schemaComponent())
        ->assertNotified('DBS Update Service check failed');

    expect($candidate->refresh()->update_service_response)->toBeNull();
});

test('the confirm step shows the vetting checklist for the candidate', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'has_dbs' => 'no',
        'dbs_certificate_number' => null,
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $html = Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->html();

    expect($html)->toContain('Confirm');
    expect($html)->toContain('Vetting Checklist');
    expect($html)->toContain('DBS');
    expect($html)->toContain('Not Barred');
    expect($html)->toContain('Overseas Police Clearance');
    expect($html)->toContain('Right to Work');
});

test('rechecking proof of address updates the match status', function () {
    Storage::fake('local');

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'address' => '19 Carlton Avenue',
        'postcode' => 'DY9 9ED',
        'proof_of_address_match' => 'no',
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $path = 'candidates/'.$candidate->id.'/proof-of-address.pdf';
    Storage::disk('local')->put($path, 'fake pdf contents');
    CandidateDocument::create([
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => DocumentType::ProofOfAddress,
        'path' => $path,
    ]);

    ProofOfAddressParser::fake([
        [
            'address' => '19 Carlton Avenue',
            'city' => 'Stourbridge',
            'county' => 'West Midlands',
            'country' => 'United Kingdom',
            'postcode' => 'DY9 9ED',
        ],
    ]);

    Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->callAction(TestAction::make('recheck_proof_of_address')->schemaComponent())
        ->assertNotified('Proof of address rechecked');

    expect($candidate->refresh()->proof_of_address_match)->toBe('yes');
});

test('rechecking proof of NI updates the match status', function () {
    Storage::fake('local');

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'ni_number' => 'QQ123456C',
        'ni_number_match' => 'no',
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $path = 'candidates/'.$candidate->id.'/proof-of-ni.pdf';
    Storage::disk('local')->put($path, 'fake pdf contents');
    CandidateDocument::create([
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => DocumentType::ProofOfNi,
        'path' => $path,
    ]);

    ProofOfNiParser::fake([['niNumber' => 'QQ123456C']]);

    Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->callAction(TestAction::make('recheck_proof_of_ni')->schemaComponent())
        ->assertNotified('Proof of NI rechecked');

    expect($candidate->refresh()->ni_number_match)->toBe('yes');
});

test('rechecking proof of address shows an error when there is no document to check', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->callAction(TestAction::make('recheck_proof_of_address')->schemaComponent())
        ->assertNotified('Unable to recheck proof of address');
});

test('manually confirming proof of address sets the match without calling the AI', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'proof_of_address_match' => 'no',
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->callAction(TestAction::make('confirm_proof_of_address')->schemaComponent())
        ->assertNotified('Proof of address manually confirmed');

    $candidate->refresh();

    expect($candidate->proof_of_address_match)->toBe('yes');
    expect($candidate->proof_of_address_checked_at)->not->toBeNull();
});

test('manually confirming proof of NI sets the match without calling the AI', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'ni_number_match' => 'no',
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->callAction(TestAction::make('confirm_proof_of_ni')->schemaComponent())
        ->assertNotified('Proof of NI manually confirmed');

    $candidate->refresh();

    expect($candidate->ni_number_match)->toBe('yes');
    expect($candidate->ni_number_checked_at)->not->toBeNull();
});

test('the confirm step submit button is labelled Complete', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $html = Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])->html();

    expect($html)->toContain('Complete');
    expect($html)->not->toContain('Save changes');
});

test('the Complete button is disabled when the vetting checklist is not fully met', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $html = Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])->html();

    expect($html)->toContain('aria-disabled="true"');
    expect($html)->toContain('All vetting checklist requirements must be met before compliance can be completed.');
    expect($html)->not->toContain('$wire.save()');
});

test('the Complete button is enabled when the vetting checklist is fully met', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'has_dbs' => 'yes',
        'dbs_certificate_number' => '001234567890',
        'barred_list_check' => 'yes',
        'lived_overseas_six_months' => 'no',
        'proof_of_address_match' => 'yes',
        'ni_number_match' => 'yes',
        'trn_number' => null,
        'safeguarding_certified_date' => now(),
        'prevent_training_completed' => 'yes',
        'right_to_work_type' => 'birth_certificate',
        'ni_number' => 'QQ123456C',
    ]);
    assignStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $skill = CandidateSkill::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);
    $candidate->skills()->attach($skill);

    foreach ([DocumentType::Cv, DocumentType::Photo, DocumentType::BirthCertificate, DocumentType::DbsFront, DocumentType::DbsBack] as $documentType) {
        CandidateDocument::create([
            'candidate_type' => EducationCandidate::class,
            'candidate_id' => $candidate->id,
            'document_type' => $documentType,
            'path' => 'candidates/'.$candidate->id.'/'.$documentType->value.'.pdf',
        ]);
    }

    $html = Livewire::test(VettingWizard::class, ['record' => $candidate->getRouteKey()])->html();

    expect($html)->not->toContain('aria-disabled="true"');
    expect($html)->toContain("confirm('Are you sure you want to confirm the compliance process is complete for this candidate?");
    expect($html)->toContain('$wire.save()');
});
