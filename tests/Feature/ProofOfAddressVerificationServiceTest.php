<?php

use App\Ai\Agents\ProofOfAddressParser;
use App\Enums\DocumentType;
use App\Models\CandidateDocument;
use App\Models\EducationCandidate;
use App\Services\ProofOfAddressVerificationService;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\LocalImage;

beforeEach(function () {
    Storage::fake('local');
});

function createProofOfAddressDocument(EducationCandidate $candidate): void
{
    $path = 'candidates/'.$candidate->id.'/proof-of-address.pdf';
    Storage::disk('local')->put($path, 'fake pdf contents');

    CandidateDocument::create([
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => DocumentType::ProofOfAddress,
        'path' => $path,
    ]);
}

test('verify throws when the candidate has no proof of address document', function () {
    $candidate = EducationCandidate::factory()->create();

    (new ProofOfAddressVerificationService)->verify($candidate);
})->throws(RuntimeException::class, 'Candidate has no proof of address document to verify.');

test('verify marks the candidate as matching when the extracted address matches', function () {
    $candidate = EducationCandidate::factory()->create([
        'address' => '19 Carlton Avenue',
        'postcode' => 'DY9 9ED',
    ]);
    createProofOfAddressDocument($candidate);

    ProofOfAddressParser::fake([
        [
            'address' => '19 Carlton Avenue',
            'city' => 'Stourbridge',
            'county' => 'West Midlands',
            'country' => 'United Kingdom',
            'postcode' => 'DY9 9ED',
        ],
    ]);

    $matches = (new ProofOfAddressVerificationService)->verify($candidate);

    expect($matches)->toBeTrue();

    $candidate->refresh();
    expect($candidate->proof_of_address_match)->toBe('yes');
    expect($candidate->proof_of_address_extracted)->toContain('19 Carlton Avenue');
    expect($candidate->proof_of_address_checked_at)->not->toBeNull();
});

test('verify marks the candidate as not matching when the postcode differs', function () {
    $candidate = EducationCandidate::factory()->create([
        'address' => '19 Carlton Avenue',
        'postcode' => 'DY9 9ED',
    ]);
    createProofOfAddressDocument($candidate);

    ProofOfAddressParser::fake([
        [
            'address' => '19 Carlton Avenue',
            'city' => 'Stourbridge',
            'county' => 'West Midlands',
            'country' => 'United Kingdom',
            'postcode' => 'SW1A 1AA',
        ],
    ]);

    $matches = (new ProofOfAddressVerificationService)->verify($candidate);

    expect($matches)->toBeFalse();
    expect($candidate->refresh()->proof_of_address_match)->toBe('no');
});

test('verify sends an image attachment as input_image, not input_file, when the document is a photo', function () {
    $candidate = EducationCandidate::factory()->create([
        'address' => '19 Carlton Avenue',
        'postcode' => 'DY9 9ED',
    ]);

    $path = 'candidates/'.$candidate->id.'/proof-of-address.jpg';
    $onePixelJpeg = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/2wBDAQMDAwQDBAgEBAgQCwkLEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBD/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAj/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=');
    Storage::disk('local')->put($path, $onePixelJpeg);

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

    (new ProofOfAddressVerificationService)->verify($candidate);

    ProofOfAddressParser::assertPrompted(
        fn ($prompt) => $prompt->attachments->first() instanceof LocalImage
    );
});

test('verify marks the candidate as not matching when the address text is very different', function () {
    $candidate = EducationCandidate::factory()->create([
        'address' => '19 Carlton Avenue',
        'postcode' => 'DY9 9ED',
    ]);
    createProofOfAddressDocument($candidate);

    ProofOfAddressParser::fake([
        [
            'address' => '42 Zebra Close',
            'city' => 'Stourbridge',
            'county' => 'West Midlands',
            'country' => 'United Kingdom',
            'postcode' => 'DY9 9ED',
        ],
    ]);

    $matches = (new ProofOfAddressVerificationService)->verify($candidate);

    expect($matches)->toBeFalse();
    expect($candidate->refresh()->proof_of_address_match)->toBe('no');
});
