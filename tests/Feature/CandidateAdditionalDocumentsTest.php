<?php

use App\Filament\Widgets\CandidateAdditionalDocuments;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
    Cache::put("user.{$this->user->id}.active_industry_id", 1);
    Storage::fake('local');
});

test('widget renders with no additional documents', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(CandidateAdditionalDocuments::class, ['record' => $candidate])
        ->assertSuccessful()
        ->assertSee('No additional documents');
});

test('the header action is labelled Add Reference, since everything added here is already a reference', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(CandidateAdditionalDocuments::class, ['record' => $candidate])
        ->assertSuccessful()
        ->assertSee('Add Reference');
});

test('a consultant can add more than one reference document for a candidate', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(CandidateAdditionalDocuments::class, ['record' => $candidate])
        ->callAction(TestAction::make('addDocument')->table(), data: [
            'file' => UploadedFile::fake()->create('reference-one.pdf', 100, 'application/pdf'),
        ])
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('addDocument')->table(), data: [
            'file' => UploadedFile::fake()->create('reference-two.pdf', 100, 'application/pdf'),
        ])
        ->assertHasNoActionErrors();

    $references = $candidate->fresh()->documents()->where('document_type', 'reference')->get();

    expect($references)->toHaveCount(2);
});

test('an other document added elsewhere still shows in the references list, but cannot be added from here', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $candidate->documents()->create(['document_type' => 'other', 'path' => 'company/education/1/other.pdf']);

    Livewire::test(CandidateAdditionalDocuments::class, ['record' => $candidate])
        ->assertSuccessful()
        ->assertSee('Other 1');
});

test('removing a reference document only deletes that document', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(CandidateAdditionalDocuments::class, ['record' => $candidate])
        ->callAction(TestAction::make('addDocument')->table(), data: ['file' => UploadedFile::fake()->create('reference-one.pdf', 100, 'application/pdf')])
        ->callAction(TestAction::make('addDocument')->table(), data: ['file' => UploadedFile::fake()->create('reference-two.pdf', 100, 'application/pdf')]);

    $references = $candidate->fresh()->documents()->where('document_type', 'reference')->get();
    $toRemove = $references->first();
    $toKeep = $references->last();

    Livewire::test(CandidateAdditionalDocuments::class, ['record' => $candidate])
        ->callAction(TestAction::make('removeAdditionalDocument')->table(record: "additional_document_{$toRemove->id}"));

    $remaining = $candidate->fresh()->documents()->where('document_type', 'reference')->get();
    expect($remaining->pluck('id')->all())->toBe([$toKeep->id]);
    Storage::disk('local')->assertMissing($toRemove->path);
});

test('the widget works for healthcare candidates too', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(CandidateAdditionalDocuments::class, ['record' => $candidate])
        ->callAction(TestAction::make('addDocument')->table(), data: [
            'file' => UploadedFile::fake()->create('reference.pdf', 100, 'application/pdf'),
        ])
        ->assertHasNoActionErrors();

    expect($candidate->fresh()->documents()->where('document_type', 'reference')->exists())->toBeTrue();
});
