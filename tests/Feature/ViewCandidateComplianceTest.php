<?php

use App\Enums\DocumentType;
use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Filament\Resources\EducationVetting\VettingResource;
use App\Models\CandidateDocument;
use App\Models\EducationApplication;
use App\Models\EducationCandidate;
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
    Storage::fake('local');
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
});

test('the compliance tab shows a link to the vetting page regardless of the candidate status', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    $html = Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->html();

    expect($html)->toContain('View Vetting');
    expect($html)->toContain(VettingResource::getUrl('edit', ['record' => $candidate]));
});

test('the compliance tab renders TRN, sanctions and restrictions', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'trn_number' => '1073430',
        'trn_issue_date' => '2020-01-15',
        'sanctions' => 'yes',
        'restrictions' => 'no',
        'sanction_restrictions_details' => 'Some sanction details here.',
        'has_naric' => 'yes',
        'has_health_condition_or_disability' => 'no',
    ]);

    $html = Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->html();

    expect($html)->toContain('1073430');
    expect($html)->toContain('15/01/2020');
    expect($html)->toContain('Some sanction details here.');
});

test('sanctions/restrictions details are hidden when both are no', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'sanctions' => 'no',
        'restrictions' => 'no',
        'sanction_restrictions_details' => 'Should not be visible.',
    ]);

    $html = Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->html();

    expect($html)->not->toContain('Sanctions / Restrictions Details');
});

test('the compliance tab shows the dbs update service result and issue date', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'dbs_certificate_number' => '001234567890',
        'update_service_response' => 'BLANK_NO_NEW_INFO',
        'update_service_checked_at' => '2026-07-10 12:00:00',
    ]);

    $html = Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->html();

    expect($html)->toContain('BLANK_NO_NEW_INFO');
    expect($html)->toContain('10/07/2026');
});

test('the call update service button is hidden without a dbs certificate number', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'dbs_certificate_number' => null,
    ]);

    $html = Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->html();

    expect($html)->not->toContain('Call Update Service');
});

test('calling the update service from the compliance tab updates the displayed result', function () {
    $this->user->company->update(['legal_name' => 'Applebough Ltd']);

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Billy',
        'last_name' => 'Jones',
        'date_of_birth' => '1990-05-15',
        'dbs_certificate_number' => '001234567890',
    ]);

    Http::fake([
        'secure.crbonline.gov.uk/*' => Http::response(<<<'XML'
            <statusCheckResult>
                <statusCheckResultType>SUCCESS</statusCheckResultType>
                <status>BLANK_NO_NEW_INFO</status>
            </statusCheckResult>
            XML),
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->callAction(TestAction::make('callUpdateService')->schemaComponent())
        ->assertNotified('DBS Update Service checked');

    $candidate->refresh();

    expect($candidate->update_service_response)->toBe('BLANK_NO_NEW_INFO');
    expect($candidate->update_service_checked_at)->not->toBeNull();
});

test('right to work shows the passport document link when type is passport', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'right_to_work_type' => 'passport',
    ]);

    CandidateDocument::create([
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => DocumentType::Passport,
        'path' => 'candidates/'.$candidate->id.'/passport.pdf',
    ]);

    $html = Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->html();

    expect($html)->toContain('UK Passport');
});

test('right to work shows visa expiry date and notes only for visa type', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'right_to_work_type' => 'visa',
        'visa_expiry_date' => '2027-06-01',
        'visa_notes' => 'Visa sponsorship confirmed.',
    ]);

    $html = Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->html();

    expect($html)->toContain('Visa');
    expect($html)->toContain('01/06/2027');
    expect($html)->toContain('Visa sponsorship confirmed.');
});

test('the compliance tab shows safeguarding, prevent training and kcsie fields', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'safeguarding_certified_date' => '2025-06-24',
        'prevent_training_completed' => 'yes',
    ]);

    EducationApplication::factory()->create([
        'education_candidate_id' => $candidate->id,
        'declaration_accepted_at' => '2025-02-04 10:00:00',
    ]);

    $html = Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->html();

    expect($html)->toContain('24/06/2025');
    expect($html)->toContain('04/02/2025');
});

test('the compliance tab shows a document view link when a safeguarding certificate is uploaded', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    $path = 'candidates/'.$candidate->id.'/safeguarding.pdf';
    Storage::disk('local')->put($path, 'fake pdf contents');

    CandidateDocument::create([
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => DocumentType::SafeguardingTraining,
        'path' => $path,
    ]);

    $html = Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->html();

    expect($html)->toContain('Uploaded');
    expect($html)->toContain('href=');
});
