<?php

use App\Models\Candidate;
use App\Models\Company;
use App\Models\ComplianceItem;
use App\Models\ComplianceItemField;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Services\Candidates\ComplianceRequirements;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create();
    $this->jobTitle = JobTitle::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
});

function attachComplianceItem(JobTitle $jobTitle, ComplianceItem $item): void
{
    $jobTitle->complianceItems()->attach($item->id, [
        'company_id' => $jobTitle->company_id,
        'industry_id' => $jobTitle->industry_id,
    ]);
}

function makeComplianceField(Company $company, Industry $industry, string $dataType): ComplianceItemField
{
    $item = ComplianceItem::factory()->create([
        'company_id' => $company->id,
        'industry_id' => $industry->id,
    ]);

    return ComplianceItemField::factory()->create([
        'compliance_item_id' => $item->id,
        'data_type' => $dataType,
    ]);
}

test('a candidate with no job title has no requirements and is trivially complete', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => null,
    ]);

    expect(ComplianceRequirements::for($candidate))->toBe([])
        ->and(ComplianceRequirements::isComplete($candidate))->toBeTrue();
});

test('a candidate with unfulfilled requirements is not complete', function () {
    $field = makeComplianceField($this->company, $this->industry, 'text');
    attachComplianceItem($this->jobTitle, $field->complianceItem);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $checks = ComplianceRequirements::for($candidate);

    expect($checks)->toHaveCount(1)
        ->and($checks[0]['complete'])->toBeFalse()
        ->and($checks[0]['fields'][0]['value'])->toBeNull()
        ->and(ComplianceRequirements::isComplete($candidate))->toBeFalse();
});

test('an item is complete once every one of its fields is filled', function () {
    $item = ComplianceItem::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
    $numberField = ComplianceItemField::factory()->create(['compliance_item_id' => $item->id, 'data_type' => 'text']);
    $issueDateField = ComplianceItemField::factory()->create(['compliance_item_id' => $item->id, 'data_type' => 'date']);
    attachComplianceItem($this->jobTitle, $item);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $candidate->complianceValues()->create(['compliance_item_field_id' => $numberField->id, 'text_value' => 'DBS-123']);

    expect(ComplianceRequirements::isComplete($candidate))->toBeFalse();

    $candidate->complianceValues()->create(['compliance_item_field_id' => $issueDateField->id, 'date_value' => now()->subMonth()]);

    expect(ComplianceRequirements::isComplete($candidate->fresh()))->toBeTrue();
});

test('a text field is complete once its text value is filled', function () {
    $field = makeComplianceField($this->company, $this->industry, 'text');
    attachComplianceItem($this->jobTitle, $field->complianceItem);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $candidate->complianceValues()->create(['compliance_item_field_id' => $field->id, 'text_value' => 'ABC123']);

    expect(ComplianceRequirements::isComplete($candidate))->toBeTrue();
});

test('a document field is complete once a document path is recorded', function () {
    $field = makeComplianceField($this->company, $this->industry, 'document');
    attachComplianceItem($this->jobTitle, $field->complianceItem);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    expect(ComplianceRequirements::isComplete($candidate))->toBeFalse();

    $candidate->complianceValues()->create(['compliance_item_field_id' => $field->id, 'document_path' => 'candidate-compliance/fake.pdf']);

    expect(ComplianceRequirements::isComplete($candidate->fresh()))->toBeTrue();
});

test('a plain date field is complete once a date is set, regardless of how far in the past it is', function () {
    $field = makeComplianceField($this->company, $this->industry, 'date');
    attachComplianceItem($this->jobTitle, $field->complianceItem);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $candidate->complianceValues()->create(['compliance_item_field_id' => $field->id, 'date_value' => now()->subYears(5)]);

    expect(ComplianceRequirements::isComplete($candidate))->toBeTrue();
});

test('a date_expiry field fails once the date has expired', function () {
    $field = makeComplianceField($this->company, $this->industry, 'date_expiry');
    attachComplianceItem($this->jobTitle, $field->complianceItem);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $candidate->complianceValues()->create(['compliance_item_field_id' => $field->id, 'date_value' => now()->subDay()]);

    expect(ComplianceRequirements::isComplete($candidate))->toBeFalse();
});

test('a date_expiry field fails when expiring within the 14 day warning window', function () {
    $field = makeComplianceField($this->company, $this->industry, 'date_expiry');
    attachComplianceItem($this->jobTitle, $field->complianceItem);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $candidate->complianceValues()->create(['compliance_item_field_id' => $field->id, 'date_value' => now()->addDays(10)]);

    expect(ComplianceRequirements::isComplete($candidate))->toBeFalse();
});

test('a date_expiry field passes when comfortably in the future', function () {
    $field = makeComplianceField($this->company, $this->industry, 'date_expiry');
    attachComplianceItem($this->jobTitle, $field->complianceItem);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $candidate->complianceValues()->create(['compliance_item_field_id' => $field->id, 'date_value' => now()->addYear()]);

    expect(ComplianceRequirements::isComplete($candidate))->toBeTrue();
});

test('isComplete requires every assigned item to pass, not just some', function () {
    $textField = makeComplianceField($this->company, $this->industry, 'text');
    $docField = makeComplianceField($this->company, $this->industry, 'document');
    attachComplianceItem($this->jobTitle, $textField->complianceItem);
    attachComplianceItem($this->jobTitle, $docField->complianceItem);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $candidate->complianceValues()->create(['compliance_item_field_id' => $textField->id, 'text_value' => 'filled']);

    expect(ComplianceRequirements::isComplete($candidate))->toBeFalse();
});
