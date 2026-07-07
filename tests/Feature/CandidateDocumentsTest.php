<?php

use App\Filament\Candidate\Pages\Documents;
use App\Models\CandidateCandidateStatus;
use App\Models\CandidateStatus;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function makeCandidateUser(string $statusName, array $candidateAttributes = []): User
{
    $company = Company::factory()->create();
    $industry = Industry::factory()->create(['slug' => 'education']);
    $candidate = EducationCandidate::factory()->create(array_merge([
        'company_id' => $company->id,
    ], $candidateAttributes));

    $status = CandidateStatus::factory()->create([
        'company_id' => $company->id,
        'industry_id' => $industry->id,
        'name' => $statusName,
    ]);

    CandidateCandidateStatus::create([
        'model_type' => EducationCandidate::class,
        'model_id' => $candidate->id,
        'candidate_status_id' => $status->id,
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);
    $user->assignRole('candidate');
    $user->industries()->attach($industry);

    return $user;
}

/** @return array<string, mixed> */
function documentTypesFor(User $user): array
{
    test()->actingAs($user);

    return Livewire::test(Documents::class)->instance()->documentTypes();
}

test('a vetting candidate is redirected from the candidate panel home to documents', function () {
    $user = makeCandidateUser('Vetting');

    $this->actingAs($user)->get('/candidate')->assertRedirect('/candidate/documents');
});

test('a vetting candidate can access the documents page directly without a redirect loop', function () {
    $user = makeCandidateUser('Vetting');

    $this->actingAs($user)->get('/candidate/documents')->assertOk();
});

test('an onboarding candidate can access the documents page', function () {
    $user = makeCandidateUser('Onboarding');

    $this->actingAs($user)->get('/candidate/documents')->assertOk();
});

test('the documents page always lists the base document types', function () {
    $user = makeCandidateUser('Onboarding');

    $types = documentTypesFor($user);

    expect(array_keys($types))->toEqual([
        'cv',
        'photo',
        'prevent_training',
        'safeguarding_training',
        'proof_of_address',
    ]);
});

test('a passport row appears when right to work is passport', function () {
    $user = makeCandidateUser('Onboarding', ['right_to_work_type' => 'passport']);

    $types = documentTypesFor($user);

    expect($types)->toHaveKey('passport');
    expect($types)->not->toHaveKey('birth_certificate');
});

test('a birth certificate row appears when right to work is birth certificate', function () {
    $user = makeCandidateUser('Onboarding', ['right_to_work_type' => 'birth_certificate']);

    $types = documentTypesFor($user);

    expect($types)->toHaveKey('birth_certificate');
    expect($types)->not->toHaveKey('passport');
});

test('no right to work document row appears when right to work is visa', function () {
    $user = makeCandidateUser('Onboarding', ['right_to_work_type' => 'visa', 'visa_share_code' => 'ABC123XYZ']);

    $types = documentTypesFor($user);

    expect($types)->not->toHaveKey('birth_certificate');
    expect($types)->not->toHaveKey('passport');
});

test('a dbs row appears when the candidate has a dbs', function () {
    $user = makeCandidateUser('Onboarding', ['has_dbs' => 'yes']);

    $types = documentTypesFor($user);

    expect($types)->toHaveKey('dbs');
    expect($types)->not->toHaveKey('proof_of_address_2');
});

test('a second proof of address row appears when the candidate has no dbs', function () {
    $user = makeCandidateUser('Onboarding', ['has_dbs' => 'no']);

    $types = documentTypesFor($user);

    expect($types)->toHaveKey('proof_of_address_2');
    expect($types)->not->toHaveKey('dbs');
});

test('a uk naric row appears when the candidate opts in', function () {
    $user = makeCandidateUser('Onboarding', ['has_naric' => 'yes']);

    $types = documentTypesFor($user);

    expect($types)->toHaveKey('uk_naric');
});

test('no uk naric row appears by default', function () {
    $user = makeCandidateUser('Onboarding');

    $types = documentTypesFor($user);

    expect($types)->not->toHaveKey('uk_naric');
});

test('the cv and photo rows reflect already-uploaded application data', function () {
    $user = makeCandidateUser('Onboarding', ['photo_path' => 'existing/photo.jpg']);
    $user->candidate->application()->create([
        'email' => $user->candidate->email,
        'status' => 'completed',
        'token' => Str::random(32),
        'expires_on' => now()->addDays(7),
        'cv_temp_path' => 'existing/cv.pdf',
    ]);

    $types = documentTypesFor($user);

    expect($types['cv']['uploaded'])->toBeTrue();
    expect($types['photo']['uploaded'])->toBeTrue();
});

test('the prevent training info action is only visible on the prevent training row', function () {
    $user = makeCandidateUser('Onboarding');

    $this->actingAs($user);

    Livewire::test(Documents::class)
        ->assertActionVisible(TestAction::make('preventTrainingInfo')->table(record: 'prevent_training'))
        ->assertActionHidden(TestAction::make('preventTrainingInfo')->table(record: 'cv'));
});
