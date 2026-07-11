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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('local');
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

test('a vetting candidate can still log out', function () {
    $user = makeCandidateUser('Vetting');

    $this->actingAs($user)
        ->post('/candidate/logout')
        ->assertRedirect();

    $this->assertGuest();
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
        'proof_of_ni',
        'dbs_front',
        'dbs_back',
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

test('dbs front and back rows appear when the candidate has a dbs', function () {
    $user = makeCandidateUser('Onboarding', ['has_dbs' => 'yes']);

    $types = documentTypesFor($user);

    expect($types)->toHaveKey('dbs_front');
    expect($types)->toHaveKey('dbs_back');
    expect($types)->not->toHaveKey('proof_of_address_2');
    expect($types)->not->toHaveKey('get_dbs');
});

test('dbs front and back rows always appear so a candidate can upload them later', function () {
    $user = makeCandidateUser('Onboarding');

    $types = documentTypesFor($user);

    expect($types)->toHaveKey('dbs_front');
    expect($types)->toHaveKey('dbs_back');
});

test('a second proof of address row and a get dbs link appear when the candidate has no dbs', function () {
    $user = makeCandidateUser('Onboarding', ['has_dbs' => 'no']);

    $types = documentTypesFor($user);

    expect($types)->toHaveKey('dbs_front');
    expect($types)->toHaveKey('dbs_back');
    expect($types)->toHaveKey('proof_of_address_2');
    expect($types)->toHaveKey('get_dbs');
    expect($types['get_dbs']['url'])->toBe(
        'https://www.hr-platform.co.uk/individual/application-login/?oo5cmxwZZpKlDaAJsRQwuW5kwPSbJcpenhQ0jtA2nYJG7djU06QdfTBNKOJlBWY97U7ETKgKu4t0%2BzZZEKG4qMqhggknGonub5UYB0YG0rL5d1LwXgaeJZr2gIfegvXtvhL8jCnjUWWs4yVQcKvxUhu0gctiD7hHaBWpsSteUWGDq%2BUGkNNzHPqHGqPenD5K4TjY7L26P7mYOq%2FAPj%2F8WQ%3D%3D'
    );
});

test('the get dbs row appears first when the candidate has no dbs', function () {
    $user = makeCandidateUser('Onboarding', ['has_dbs' => 'no']);

    $types = documentTypesFor($user);

    expect(array_key_first($types))->toBe('get_dbs');
});

test('the get dbs action links out and the upload action is hidden for that row', function () {
    $user = makeCandidateUser('Onboarding', ['has_dbs' => 'no']);
    $this->actingAs($user);

    Livewire::test(Documents::class)
        ->assertActionVisible(TestAction::make('getDbs')->table(record: 'get_dbs'))
        ->assertActionHidden(TestAction::make('upload')->table(record: 'get_dbs'))
        ->assertActionHidden(TestAction::make('update')->table(record: 'get_dbs'))
        ->assertActionHidden(TestAction::make('remove')->table(record: 'get_dbs'));
});

test('a candidate can upload the front of their dbs after previously saying they had none', function () {
    $user = makeCandidateUser('Onboarding', ['has_dbs' => 'no']);
    $this->actingAs($user);

    $file = UploadedFile::fake()->create('dbs-front.pdf', 100, 'application/pdf');

    Livewire::test(Documents::class)
        ->callAction(
            TestAction::make('upload')->table(record: 'dbs_front'),
            data: ['file' => $file],
        )
        ->assertHasNoActionErrors();

    $document = $user->candidate->fresh()->documents()->where('document_type', 'dbs_front')->first();

    expect($document)->not->toBeNull();
    Storage::disk('local')->assertExists($document->path);
});

test('a candidate can upload the back of their dbs separately from the front', function () {
    $user = makeCandidateUser('Onboarding', ['has_dbs' => 'no']);
    $this->actingAs($user);

    $file = UploadedFile::fake()->create('dbs-back.pdf', 100, 'application/pdf');

    Livewire::test(Documents::class)
        ->callAction(
            TestAction::make('upload')->table(record: 'dbs_back'),
            data: ['file' => $file],
        )
        ->assertHasNoActionErrors();

    $document = $user->candidate->fresh()->documents()->where('document_type', 'dbs_back')->first();

    expect($document)->not->toBeNull();
    Storage::disk('local')->assertExists($document->path);
});

test('uploading either side of the dbs sets has_dbs to yes and removes the get dbs row', function () {
    $user = makeCandidateUser('Onboarding', ['has_dbs' => 'no']);
    $this->actingAs($user);

    $file = UploadedFile::fake()->create('dbs-front.pdf', 100, 'application/pdf');

    Livewire::test(Documents::class)
        ->callAction(
            TestAction::make('upload')->table(record: 'dbs_front'),
            data: ['file' => $file],
        )
        ->assertHasNoActionErrors();

    expect($user->candidate->fresh()->has_dbs)->toBe('yes');

    $types = documentTypesFor($user);
    expect($types)->not->toHaveKey('get_dbs');
});

test('the actions tab only lists documents that have not been uploaded yet', function () {
    $user = makeCandidateUser('Onboarding', ['has_dbs' => 'no']);
    $user->candidate->documents()->create(['document_type' => 'cv', 'path' => 'company/education/1/cv.pdf']);
    $this->actingAs($user);

    Livewire::test(Documents::class)
        ->set('activeTab', 'actions')
        ->assertSee('Photo')
        ->assertSee('Get your DBS')
        ->assertDontSee('CV');
});

test('the documents tab only lists documents that have been uploaded', function () {
    $user = makeCandidateUser('Onboarding', ['has_dbs' => 'no']);
    $user->candidate->documents()->create(['document_type' => 'cv', 'path' => 'company/education/1/cv.pdf']);
    $this->actingAs($user);

    Livewire::test(Documents::class)
        ->set('activeTab', 'documents')
        ->assertSee('CV')
        ->assertDontSee('Photo')
        ->assertDontSee('Get your DBS');
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

test('rows reflect existing candidate_documents records', function () {
    $user = makeCandidateUser('Onboarding');
    $user->candidate->documents()->createMany([
        ['document_type' => 'cv', 'path' => 'company/education/1/cv.pdf'],
        ['document_type' => 'photo', 'path' => 'company/education/1/photo.jpg'],
    ]);

    $types = documentTypesFor($user);

    expect($types['cv']['uploaded'])->toBeTrue();
    expect($types['cv']['path'])->toBe('company/education/1/cv.pdf');
    expect($types['photo']['uploaded'])->toBeTrue();
    expect($types['photo']['path'])->toBe('company/education/1/photo.jpg');
});

test('the prevent training info action is only visible on the prevent training row', function () {
    $user = makeCandidateUser('Onboarding');

    $this->actingAs($user);

    Livewire::test(Documents::class)
        ->assertActionVisible(TestAction::make('preventTrainingInfo')->table(record: 'prevent_training'))
        ->assertActionHidden(TestAction::make('preventTrainingInfo')->table(record: 'cv'));
});

test('uploading a document creates a candidate_documents record and stores the file', function () {
    $user = makeCandidateUser('Onboarding');
    $this->actingAs($user);

    $file = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');

    Livewire::test(Documents::class)
        ->callAction(
            TestAction::make('upload')->table(record: 'cv'),
            data: ['file' => $file],
        )
        ->assertHasNoActionErrors();

    $document = $user->candidate->fresh()->documents()->where('document_type', 'cv')->first();

    expect($document)->not->toBeNull();
    Storage::disk('local')->assertExists($document->path);
});

test('uploading a proof of ni document creates a candidate_documents record and stores the file', function () {
    $user = makeCandidateUser('Onboarding');
    $this->actingAs($user);

    $file = UploadedFile::fake()->create('proof-of-ni.pdf', 100, 'application/pdf');

    Livewire::test(Documents::class)
        ->callAction(
            TestAction::make('upload')->table(record: 'proof_of_ni'),
            data: ['file' => $file],
        )
        ->assertHasNoActionErrors();

    $document = $user->candidate->fresh()->documents()->where('document_type', 'proof_of_ni')->first();

    expect($document)->not->toBeNull();
    Storage::disk('local')->assertExists($document->path);
});

test('updating a document replaces the stored file and record', function () {
    $user = makeCandidateUser('Onboarding');
    $this->actingAs($user);

    $originalFile = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');

    Livewire::test(Documents::class)
        ->callAction(TestAction::make('upload')->table(record: 'cv'), data: ['file' => $originalFile]);

    $originalDocument = $user->candidate->fresh()->documents()->where('document_type', 'cv')->first();
    $originalPath = $originalDocument->path;

    $newFile = UploadedFile::fake()->create('cv-updated.pdf', 100, 'application/pdf');

    Livewire::test(Documents::class)
        ->set('activeTab', 'documents')
        ->callAction(
            TestAction::make('update')->table(record: 'cv'),
            data: ['file' => $newFile],
        )
        ->assertHasNoActionErrors();

    $candidate = $user->candidate->fresh();
    expect($candidate->documents()->where('document_type', 'cv')->count())->toBe(1);

    $updatedDocument = $candidate->documents()->where('document_type', 'cv')->first();
    expect($updatedDocument->id)->toBe($originalDocument->id);
    expect($updatedDocument->path)->not->toBe($originalPath);

    Storage::disk('local')->assertMissing($originalPath);
    Storage::disk('local')->assertExists($updatedDocument->path);
});

test('removing a document deletes the stored file and record', function () {
    $user = makeCandidateUser('Onboarding');
    $this->actingAs($user);

    $file = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');

    Livewire::test(Documents::class)
        ->callAction(TestAction::make('upload')->table(record: 'cv'), data: ['file' => $file]);

    $document = $user->candidate->fresh()->documents()->where('document_type', 'cv')->first();
    $path = $document->path;

    Livewire::test(Documents::class)
        ->set('activeTab', 'documents')
        ->callAction(TestAction::make('remove')->table(record: 'cv'));

    expect($user->candidate->fresh()->documents()->where('document_type', 'cv')->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($path);
});

test('uploaded documents are stored under the company and industry name, not their ids', function () {
    $user = makeCandidateUser('Onboarding');
    $this->actingAs($user);

    $file = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');

    Livewire::test(Documents::class)
        ->callAction(TestAction::make('upload')->table(record: 'cv'), data: ['file' => $file]);

    $candidate = $user->candidate->fresh();
    $document = $candidate->documents()->where('document_type', 'cv')->first();

    $expectedCompanySlug = Str::slug($candidate->company->name);
    $expectedIndustrySlug = Str::slug(Industry::where('slug', 'education')->value('name'));

    expect($document->path)->toStartWith("{$expectedCompanySlug}/{$expectedIndustrySlug}/{$candidate->id}/");
});
