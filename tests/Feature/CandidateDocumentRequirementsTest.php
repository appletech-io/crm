<?php

use App\Models\Candidate;
use App\Models\Company;
use App\Models\ComplianceItem;
use App\Models\ComplianceItemField;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
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

test('a document-type compliance item field is merged into the candidate documents list, but a text-type field is not', function () {
    $company = Company::factory()->create();
    $industry = Industry::factory()->create();
    $jobTitle = JobTitle::factory()->create(['company_id' => $company->id, 'industry_id' => $industry->id]);

    $item = ComplianceItem::factory()->create(['company_id' => $company->id, 'industry_id' => $industry->id, 'name' => 'Right to Work']);
    $docField = ComplianceItemField::factory()->create(['compliance_item_id' => $item->id, 'data_type' => 'document', 'name' => 'Document Upload']);
    $textField = ComplianceItemField::factory()->create(['compliance_item_id' => $item->id, 'data_type' => 'text', 'name' => 'Document Type']);
    $jobTitle->complianceItems()->attach($item->id, ['company_id' => $company->id, 'industry_id' => $industry->id]);

    $candidate = Candidate::factory()->create([
        'company_id' => $company->id,
        'industry_id' => $industry->id,
        'job_title_id' => $jobTitle->id,
    ]);

    $rows = collect(CandidateDocumentRequirements::for($candidate))->keyBy('document_type');

    expect($rows)->toHaveKey("compliance_field_{$docField->id}")
        ->and($rows)->not->toHaveKey("compliance_field_{$textField->id}");

    $row = $rows->get("compliance_field_{$docField->id}");
    expect($row['label'])->toBe('Right to Work: Document Upload')
        ->and($row['uploaded'])->toBeFalse();

    $candidate->complianceValues()->create(['compliance_item_field_id' => $docField->id, 'document_path' => 'candidate-compliance/fake.pdf']);

    $refreshedRow = collect(CandidateDocumentRequirements::for($candidate->fresh()))->keyBy('document_type')->get("compliance_field_{$docField->id}");
    expect($refreshedRow['uploaded'])->toBeTrue()
        ->and($refreshedRow['path'])->toBe('candidate-compliance/fake.pdf');
});
