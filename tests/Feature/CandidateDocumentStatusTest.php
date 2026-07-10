<?php

use App\Filament\Widgets\CandidateDocumentStatus;
use App\Models\CandidateDocument;
use App\Models\EducationCandidate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
    Cache::put("user.{$this->user->id}.active_industry_id", 1);
});

test('widget renders', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(CandidateDocumentStatus::class, ['record' => $candidate])
        ->assertSuccessful();
});

test('widget shows required documents based on the candidates application form answers', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'right_to_work_type' => 'passport',
        'has_dbs' => 'no',
        'has_naric' => 'yes',
    ]);

    $html = Livewire::test(CandidateDocumentStatus::class, ['record' => $candidate])
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

    $html = Livewire::test(CandidateDocumentStatus::class, ['record' => $candidate])
        ->assertSuccessful()
        ->html();

    expect($html)->not->toContain('Get your DBS');
});

test('widget marks a document as uploaded once it exists', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    CandidateDocument::create([
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => 'cv',
        'path' => 'fake/path/cv.pdf',
    ]);

    $html = Livewire::test(CandidateDocumentStatus::class, ['record' => $candidate])
        ->assertSuccessful()
        ->html();

    expect($html)->toContain('Uploaded');
    expect($html)->toContain('Not uploaded');
});
