<?php

use App\Ai\Agents\ProofOfNiParser;
use App\Enums\DocumentType;
use App\Models\CandidateDocument;
use App\Models\EducationCandidate;
use App\Services\NiNumberVerificationService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function createProofOfNiDocument(EducationCandidate $candidate): void
{
    $path = 'candidates/'.$candidate->id.'/proof-of-ni.pdf';
    Storage::disk('local')->put($path, 'fake pdf contents');

    CandidateDocument::create([
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => DocumentType::ProofOfNi,
        'path' => $path,
    ]);
}

test('verify throws when the candidate has no proof of NI document', function () {
    $candidate = EducationCandidate::factory()->create();

    (new NiNumberVerificationService)->verify($candidate);
})->throws(RuntimeException::class, 'Candidate has no proof of NI document to verify.');

test('verify marks the candidate as matching when the extracted NI number matches', function () {
    $candidate = EducationCandidate::factory()->create(['ni_number' => 'QQ123456C']);
    createProofOfNiDocument($candidate);

    ProofOfNiParser::fake([['niNumber' => 'QQ 12 34 56 C']]);

    $matches = (new NiNumberVerificationService)->verify($candidate);

    expect($matches)->toBeTrue();

    $candidate->refresh();
    expect($candidate->ni_number_match)->toBe('yes');
    expect($candidate->ni_number_extracted)->toBe('QQ 12 34 56 C');
    expect($candidate->ni_number_checked_at)->not->toBeNull();
});

test('verify marks the candidate as not matching when the extracted NI number differs', function () {
    $candidate = EducationCandidate::factory()->create(['ni_number' => 'QQ123456C']);
    createProofOfNiDocument($candidate);

    ProofOfNiParser::fake([['niNumber' => 'AB987654D']]);

    $matches = (new NiNumberVerificationService)->verify($candidate);

    expect($matches)->toBeFalse();
    expect($candidate->refresh()->ni_number_match)->toBe('no');
});

test('verify marks the candidate as not matching when nothing was extracted', function () {
    $candidate = EducationCandidate::factory()->create(['ni_number' => 'QQ123456C']);
    createProofOfNiDocument($candidate);

    ProofOfNiParser::fake([['niNumber' => null]]);

    $matches = (new NiNumberVerificationService)->verify($candidate);

    expect($matches)->toBeFalse();
    expect($candidate->refresh()->ni_number_match)->toBe('no');
});
