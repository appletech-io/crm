<?php

use App\Models\Candidate;
use App\Models\EducationCandidate;
use App\Services\Candidates\CandidateDocumentRequirements;

test('a plain candidate gets no dbs/right-to-work document rows, since Compliance Items cover that instead', function () {
    $candidate = Candidate::factory()->create();

    $types = collect(CandidateDocumentRequirements::for($candidate))->pluck('document_type')->all();

    expect($types)->toBe(['cv', 'photo', 'proof_of_address', 'proof_of_ni']);
});

test('an education candidate still gets its dbs/right-to-work document rows unaffected', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null, 'right_to_work_type' => 'passport']);

    $types = collect(CandidateDocumentRequirements::for($candidate))->pluck('document_type')->all();

    expect($types)->toContain('dbs_front')
        ->toContain('dbs_back')
        ->toContain('passport');
});
