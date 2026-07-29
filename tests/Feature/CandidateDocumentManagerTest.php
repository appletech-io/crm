<?php

use App\Filament\Widgets\CandidateDocumentManager;
use App\Models\EducationCandidate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('local');

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
    Cache::put("user.{$this->user->id}.active_industry_id", 1);
});

test('widget renders', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(CandidateDocumentManager::class, ['record' => $candidate])
        ->assertSuccessful();
});

test('widget shows required documents based on the candidates application form answers', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'right_to_work_type' => 'passport',
        'has_dbs' => 'no',
        'has_naric' => 'yes',
    ]);

    $html = Livewire::test(CandidateDocumentManager::class, ['record' => $candidate])
        ->assertSuccessful()
        ->html();

    expect($html)->toContain('CV');
    expect($html)->toContain('Photo');
    expect($html)->toContain('Passport');
    expect($html)->toContain('DBS');
    expect($html)->toContain('Proof of Address 2');
    expect($html)->toContain('UK NARIC');
    expect($html)->not->toContain('Birth Certificate');
});

test('widget never shows the candidate-only get dbs link', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'has_dbs' => 'no',
    ]);

    $html = Livewire::test(CandidateDocumentManager::class, ['record' => $candidate])
        ->assertSuccessful()
        ->html();

    expect($html)->not->toContain('Get your DBS');
});

test('the upload action is visible when a document has not been uploaded', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(CandidateDocumentManager::class, ['record' => $candidate])
        ->assertActionVisible(TestAction::make('upload')->table(record: 'cv'))
        ->assertActionHidden(TestAction::make('update')->table(record: 'cv'))
        ->assertActionHidden(TestAction::make('view')->table(record: 'cv'));
});

test('a document can be uploaded', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $file = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');

    Livewire::test(CandidateDocumentManager::class, ['record' => $candidate])
        ->callAction(
            TestAction::make('upload')->table(record: 'cv'),
            data: ['file' => $file],
        )
        ->assertHasNoActionErrors();

    $document = $candidate->fresh()->documents()->where('document_type', 'cv')->first();

    expect($document)->not->toBeNull();
    Storage::disk('local')->assertExists($document->path);
});

test('the table reflects an upload immediately, without needing a fresh page load', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $file = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');

    $component = Livewire::test(CandidateDocumentManager::class, ['record' => $candidate])
        ->assertActionVisible(TestAction::make('upload')->table(record: 'cv'))
        ->assertActionHidden(TestAction::make('update')->table(record: 'cv'));

    $component->callAction(
        TestAction::make('upload')->table(record: 'cv'),
        data: ['file' => $file],
    )->assertHasNoActionErrors();

    $component
        ->assertActionHidden(TestAction::make('upload')->table(record: 'cv'))
        ->assertActionVisible(TestAction::make('update')->table(record: 'cv'))
        ->assertActionVisible(TestAction::make('view')->table(record: 'cv'));
});

test('once uploaded, the update and view actions replace the upload action', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $candidate->documents()->create(['document_type' => 'cv', 'path' => 'fake/path/cv.pdf']);

    Livewire::test(CandidateDocumentManager::class, ['record' => $candidate])
        ->assertActionHidden(TestAction::make('upload')->table(record: 'cv'))
        ->assertActionVisible(TestAction::make('update')->table(record: 'cv'))
        ->assertActionVisible(TestAction::make('view')->table(record: 'cv'));
});

test('a document can be updated, replacing the stored file and record', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $originalFile = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');

    Livewire::test(CandidateDocumentManager::class, ['record' => $candidate])
        ->callAction(TestAction::make('upload')->table(record: 'cv'), data: ['file' => $originalFile]);

    $originalDocument = $candidate->fresh()->documents()->where('document_type', 'cv')->first();
    $originalPath = $originalDocument->path;

    $newFile = UploadedFile::fake()->create('cv-updated.pdf', 100, 'application/pdf');

    Livewire::test(CandidateDocumentManager::class, ['record' => $candidate])
        ->callAction(
            TestAction::make('update')->table(record: 'cv'),
            data: ['file' => $newFile],
        )
        ->assertHasNoActionErrors();

    $candidate->refresh();
    expect($candidate->documents()->where('document_type', 'cv')->count())->toBe(1);

    $updatedDocument = $candidate->documents()->where('document_type', 'cv')->first();
    expect($updatedDocument->id)->toBe($originalDocument->id);
    expect($updatedDocument->path)->not->toBe($originalPath);

    Storage::disk('local')->assertMissing($originalPath);
    Storage::disk('local')->assertExists($updatedDocument->path);
});

test('a document can be removed', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $file = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');

    Livewire::test(CandidateDocumentManager::class, ['record' => $candidate])
        ->callAction(TestAction::make('upload')->table(record: 'cv'), data: ['file' => $file]);

    $document = $candidate->fresh()->documents()->where('document_type', 'cv')->first();
    $path = $document->path;

    Livewire::test(CandidateDocumentManager::class, ['record' => $candidate])
        ->callAction(TestAction::make('remove')->table(record: 'cv'));

    expect($candidate->fresh()->documents()->where('document_type', 'cv')->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($path);
});

test('uploading either side of the dbs sets has_dbs to yes', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'has_dbs' => 'no',
    ]);
    $file = UploadedFile::fake()->create('dbs-front.pdf', 100, 'application/pdf');

    Livewire::test(CandidateDocumentManager::class, ['record' => $candidate])
        ->callAction(
            TestAction::make('upload')->table(record: 'dbs_front'),
            data: ['file' => $file],
        )
        ->assertHasNoActionErrors();

    expect($candidate->fresh()->has_dbs)->toBe('yes');
});

test('a consultant can add a reference document by picking the type from the dropdown', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(CandidateDocumentManager::class, ['record' => $candidate])
        ->callAction(TestAction::make('addDocument')->table(), data: [
            'document_type' => 'reference',
            'file' => UploadedFile::fake()->create('reference.pdf', 100, 'application/pdf'),
        ])
        ->assertHasNoActionErrors();

    expect($candidate->fresh()->documents()->where('document_type', 'reference')->exists())->toBeTrue();
});

test('a consultant can add more than one other document via the same dropdown', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(CandidateDocumentManager::class, ['record' => $candidate])
        ->callAction(TestAction::make('addDocument')->table(), data: [
            'document_type' => 'other',
            'file' => UploadedFile::fake()->create('misc-one.pdf', 100, 'application/pdf'),
        ])
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('addDocument')->table(), data: [
            'document_type' => 'other',
            'file' => UploadedFile::fake()->create('misc-two.pdf', 100, 'application/pdf'),
        ])
        ->assertHasNoActionErrors();

    expect($candidate->fresh()->documents()->where('document_type', 'other')->count())->toBe(2);
});

test('a consultant can add more than one qualification document via the same dropdown', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(CandidateDocumentManager::class, ['record' => $candidate])
        ->callAction(TestAction::make('addDocument')->table(), data: [
            'document_type' => 'qualification',
            'file' => UploadedFile::fake()->create('degree.pdf', 100, 'application/pdf'),
        ])
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('addDocument')->table(), data: [
            'document_type' => 'qualification',
            'file' => UploadedFile::fake()->create('certificate.pdf', 100, 'application/pdf'),
        ])
        ->assertHasNoActionErrors();

    expect($candidate->fresh()->documents()->where('document_type', 'qualification')->count())->toBe(2);
});

test('an additional document can be given a name, which is then used as its label', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    $html = Livewire::test(CandidateDocumentManager::class, ['record' => $candidate])
        ->callAction(TestAction::make('addDocument')->table(), data: [
            'name' => 'BSc Nursing Certificate',
            'document_type' => 'qualification',
            'file' => UploadedFile::fake()->create('degree.pdf', 100, 'application/pdf'),
        ])
        ->assertHasNoActionErrors()
        ->html();

    $document = $candidate->fresh()->documents()->where('document_type', 'qualification')->first();

    expect($document->name)->toBe('BSc Nursing Certificate');
    expect($html)->toContain('BSc Nursing Certificate');
    expect($html)->not->toContain('Qualification 1');
});

test('an additional document without a name falls back to the generated type label', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    $html = Livewire::test(CandidateDocumentManager::class, ['record' => $candidate])
        ->callAction(TestAction::make('addDocument')->table(), data: [
            'document_type' => 'qualification',
            'file' => UploadedFile::fake()->create('degree.pdf', 100, 'application/pdf'),
        ])
        ->assertHasNoActionErrors()
        ->html();

    expect($html)->toContain('Qualification 1');
});

test('removing an additional document only deletes that document, leaving required documents alone', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $candidate->documents()->create(['document_type' => 'cv', 'path' => 'fake/path/cv.pdf']);

    Livewire::test(CandidateDocumentManager::class, ['record' => $candidate])
        ->callAction(TestAction::make('addDocument')->table(), data: [
            'document_type' => 'reference',
            'file' => UploadedFile::fake()->create('reference.pdf', 100, 'application/pdf'),
        ]);

    $reference = $candidate->fresh()->documents()->where('document_type', 'reference')->first();

    Livewire::test(CandidateDocumentManager::class, ['record' => $candidate])
        ->callAction(TestAction::make('removeAdditionalDocument')->table(record: "additional_document_{$reference->id}"));

    expect($candidate->fresh()->documents()->where('document_type', 'reference')->exists())->toBeFalse();
    expect($candidate->fresh()->documents()->where('document_type', 'cv')->exists())->toBeTrue();
    Storage::disk('local')->assertMissing($reference->path);
});
