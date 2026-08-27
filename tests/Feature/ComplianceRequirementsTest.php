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

// for() — every Compliance Item for the candidate's company/industry,
// independent of job title (a candidate can fill out any of them).

test('for() returns no items when none exist for the company/industry', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => null,
    ]);

    expect(ComplianceRequirements::for($candidate))->toBe([]);
});

test('for() includes an item even when it is not attached to any job title', function () {
    $item = ComplianceItem::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
    ComplianceItemField::factory()->create(['compliance_item_id' => $item->id, 'data_type' => 'text']);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => null,
    ]);

    $checks = ComplianceRequirements::for($candidate);

    expect($checks)->toHaveCount(1)
        ->and($checks[0]['item']->id)->toBe($item->id);
});

test('for() includes an item attached only to a different job title than the candidate\'s own', function () {
    $item = ComplianceItem::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
    ComplianceItemField::factory()->create(['compliance_item_id' => $item->id, 'data_type' => 'text']);
    $otherJobTitle = JobTitle::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    attachComplianceItem($otherJobTitle, $item);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    expect(ComplianceRequirements::for($candidate))->toHaveCount(1);
});

test('for() does not include an item scoped to a different company or industry', function () {
    ComplianceItem::factory()->create();

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => null,
    ]);

    expect(ComplianceRequirements::for($candidate))->toBe([]);
});

// forJobTitle() / isCompleteForJobTitle() — just the items a specific job
// title requires, used for "is this candidate eligible to work as X".

test('forJobTitle returns nothing for a null job title', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => null,
    ]);

    expect(ComplianceRequirements::forJobTitle($candidate, null))->toBe([])
        ->and(ComplianceRequirements::isCompleteForJobTitle($candidate, null))->toBeTrue();
});

test('a candidate with unfulfilled requirements is not complete for that job title', function () {
    $field = makeComplianceField($this->company, $this->industry, 'text');
    attachComplianceItem($this->jobTitle, $field->complianceItem);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $checks = ComplianceRequirements::forJobTitle($candidate, $this->jobTitle);

    expect($checks)->toHaveCount(1)
        ->and($checks[0]['complete'])->toBeFalse()
        ->and($checks[0]['fields'][0]['value'])->toBeNull()
        ->and(ComplianceRequirements::isCompleteForJobTitle($candidate, $this->jobTitle))->toBeFalse();
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

    expect(ComplianceRequirements::isCompleteForJobTitle($candidate, $this->jobTitle))->toBeFalse();

    $candidate->complianceValues()->create(['compliance_item_field_id' => $issueDateField->id, 'date_value' => now()->subMonth()]);

    expect(ComplianceRequirements::isCompleteForJobTitle($candidate->fresh(), $this->jobTitle))->toBeTrue();
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

    expect(ComplianceRequirements::isCompleteForJobTitle($candidate, $this->jobTitle))->toBeTrue();
});

test('a document field is complete once a document path is recorded', function () {
    $field = makeComplianceField($this->company, $this->industry, 'document');
    attachComplianceItem($this->jobTitle, $field->complianceItem);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    expect(ComplianceRequirements::isCompleteForJobTitle($candidate, $this->jobTitle))->toBeFalse();

    $candidate->complianceValues()->create(['compliance_item_field_id' => $field->id, 'document_path' => 'candidate-compliance/fake.pdf']);

    expect(ComplianceRequirements::isCompleteForJobTitle($candidate->fresh(), $this->jobTitle))->toBeTrue();
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

    expect(ComplianceRequirements::isCompleteForJobTitle($candidate, $this->jobTitle))->toBeTrue();
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

    expect(ComplianceRequirements::isCompleteForJobTitle($candidate, $this->jobTitle))->toBeFalse();
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

    expect(ComplianceRequirements::isCompleteForJobTitle($candidate, $this->jobTitle))->toBeFalse();
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

    expect(ComplianceRequirements::isCompleteForJobTitle($candidate, $this->jobTitle))->toBeTrue();
});

test('isCompleteForJobTitle requires every assigned item to pass, not just some', function () {
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

    expect(ComplianceRequirements::isCompleteForJobTitle($candidate, $this->jobTitle))->toBeFalse();
});

test('forJobTitle ignores an item required by a different job title than the one asked about', function () {
    $field = makeComplianceField($this->company, $this->industry, 'text');
    $otherJobTitle = JobTitle::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    attachComplianceItem($otherJobTitle, $field->complianceItem);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    expect(ComplianceRequirements::forJobTitle($candidate, $this->jobTitle))->toBe([])
        ->and(ComplianceRequirements::isCompleteForJobTitle($candidate, $this->jobTitle))->toBeTrue();
});
