<?php

use App\Enums\CandidateAvailabilityStatus;
use App\Models\Candidate;
use App\Models\CandidateAvailability;
use App\Models\ComplianceItem;
use App\Models\ComplianceItemField;
use App\Models\EducationCandidate;
use App\Models\JobTitle;
use App\Models\Qualification;
use App\Services\Booking\BookingEligibility;

test('disallowedJobTitleReason returns null when the candidate has no qualification', function () {
    $candidate = EducationCandidate::factory()->create(['qualification_id' => null]);
    $jobTitle = JobTitle::factory()->create();

    expect(BookingEligibility::disallowedJobTitleReason($candidate, $jobTitle->id))->toBeNull();
});

test('disallowedJobTitleReason returns null when the qualification has no allowed job titles configured', function () {
    $qualification = Qualification::factory()->create();
    $candidate = EducationCandidate::factory()->create(['qualification_id' => $qualification->id]);
    $jobTitle = JobTitle::factory()->create();

    expect(BookingEligibility::disallowedJobTitleReason($candidate, $jobTitle->id))->toBeNull();
});

test('disallowedJobTitleReason returns null when the job title is in the qualification\'s allowed list', function () {
    $qualification = Qualification::factory()->create();
    $jobTitle = JobTitle::factory()->create();
    $qualification->jobTitles()->attach($jobTitle->id, ['company_id' => $qualification->company_id, 'industry_id' => $qualification->industry_id]);

    $candidate = EducationCandidate::factory()->create(['qualification_id' => $qualification->id]);

    expect(BookingEligibility::disallowedJobTitleReason($candidate, $jobTitle->id))->toBeNull();
});

test('disallowedJobTitleReason returns a reason when the job title is not in the qualification\'s allowed list', function () {
    $qualification = Qualification::factory()->create(['name' => 'PGCE']);
    $allowedJobTitle = JobTitle::factory()->create();
    $qualification->jobTitles()->attach($allowedJobTitle->id, ['company_id' => $qualification->company_id, 'industry_id' => $qualification->industry_id]);

    $disallowedJobTitle = JobTitle::factory()->create(['name' => 'Headteacher']);
    $candidate = EducationCandidate::factory()->create(['qualification_id' => $qualification->id]);

    $reason = BookingEligibility::disallowedJobTitleReason($candidate, $disallowedJobTitle->id);

    expect($reason)->not->toBeNull();
    expect($reason)->toContain('PGCE');
    expect($reason)->toContain('Headteacher');
});

test('disallowedJobTitleReason returns null with no candidate or no job title', function () {
    $qualification = Qualification::factory()->create();
    $candidate = EducationCandidate::factory()->create(['qualification_id' => $qualification->id]);

    expect(BookingEligibility::disallowedJobTitleReason(null, 1))->toBeNull();
    expect(BookingEligibility::disallowedJobTitleReason($candidate, null))->toBeNull();
});

test('disallowedJobTitleReason blocks a generic candidate missing a job title\'s required compliance item, naming it', function () {
    $jobTitle = JobTitle::factory()->create();
    $item = ComplianceItem::factory()->create(['company_id' => $jobTitle->company_id, 'industry_id' => $jobTitle->industry_id, 'name' => 'DBS Check']);
    ComplianceItemField::factory()->create(['compliance_item_id' => $item->id, 'data_type' => 'text']);
    $jobTitle->complianceItems()->attach($item->id, ['company_id' => $jobTitle->company_id, 'industry_id' => $jobTitle->industry_id]);

    $candidate = Candidate::factory()->create(['company_id' => $jobTitle->company_id, 'industry_id' => $jobTitle->industry_id]);

    $reason = BookingEligibility::disallowedJobTitleReason($candidate, $jobTitle->id);

    expect($reason)->not->toBeNull()
        ->and($reason)->toContain('DBS Check')
        ->and($reason)->toContain($jobTitle->name);
});

test('disallowedJobTitleReason allows a generic candidate who has completed everything the job title requires', function () {
    $jobTitle = JobTitle::factory()->create();
    $item = ComplianceItem::factory()->create(['company_id' => $jobTitle->company_id, 'industry_id' => $jobTitle->industry_id]);
    $field = ComplianceItemField::factory()->create(['compliance_item_id' => $item->id, 'data_type' => 'text']);
    $jobTitle->complianceItems()->attach($item->id, ['company_id' => $jobTitle->company_id, 'industry_id' => $jobTitle->industry_id]);

    $candidate = Candidate::factory()->create(['company_id' => $jobTitle->company_id, 'industry_id' => $jobTitle->industry_id]);
    $candidate->complianceValues()->create(['compliance_item_field_id' => $field->id, 'text_value' => 'filled']);

    expect(BookingEligibility::disallowedJobTitleReason($candidate, $jobTitle->id))->toBeNull();
});

test('disallowedJobTitleReason allows a generic candidate for a job title with no compliance items configured', function () {
    $jobTitle = JobTitle::factory()->create();
    $candidate = Candidate::factory()->create(['company_id' => $jobTitle->company_id, 'industry_id' => $jobTitle->industry_id]);

    expect(BookingEligibility::disallowedJobTitleReason($candidate, $jobTitle->id))->toBeNull();
});

test('disallowedJobTitleReason never runs the compliance check for an Education candidate, only the qualification one', function () {
    $jobTitle = JobTitle::factory()->create();
    $item = ComplianceItem::factory()->create(['company_id' => $jobTitle->company_id, 'industry_id' => $jobTitle->industry_id]);
    ComplianceItemField::factory()->create(['compliance_item_id' => $item->id, 'data_type' => 'text']);
    $jobTitle->complianceItems()->attach($item->id, ['company_id' => $jobTitle->company_id, 'industry_id' => $jobTitle->industry_id]);

    $candidate = EducationCandidate::factory()->create(['qualification_id' => null]);

    expect(BookingEligibility::disallowedJobTitleReason($candidate, $jobTitle->id))->toBeNull();
});

test('unavailableDates includes a date the candidate marked not available', function () {
    $candidate = EducationCandidate::factory()->create();

    CandidateAvailability::create([
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'date' => '2026-08-03',
        'status' => CandidateAvailabilityStatus::NotAvailable,
    ]);

    $result = BookingEligibility::unavailableDates(EducationCandidate::class, $candidate->id, [
        ['date' => '2026-08-03', 'period' => 'full_day'],
    ]);

    expect($result->all())->toBe(['2026-08-03']);
});

test('unavailableDates includes a full day request when the candidate is only available in the morning', function () {
    $candidate = EducationCandidate::factory()->create();

    CandidateAvailability::create([
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'date' => '2026-08-03',
        'status' => CandidateAvailabilityStatus::AvailableAm,
    ]);

    $result = BookingEligibility::unavailableDates(EducationCandidate::class, $candidate->id, [
        ['date' => '2026-08-03', 'period' => 'full_day'],
    ]);

    expect($result->all())->toBe(['2026-08-03']);
});

test('unavailableDates excludes a morning request when the candidate is only available in the morning', function () {
    $candidate = EducationCandidate::factory()->create();

    CandidateAvailability::create([
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'date' => '2026-08-03',
        'status' => CandidateAvailabilityStatus::AvailableAm,
    ]);

    $result = BookingEligibility::unavailableDates(EducationCandidate::class, $candidate->id, [
        ['date' => '2026-08-03', 'period' => 'am'],
    ]);

    expect($result->all())->toBe([]);
});

test('unavailableDates excludes a date with no availability record at all', function () {
    $candidate = EducationCandidate::factory()->create();

    $result = BookingEligibility::unavailableDates(EducationCandidate::class, $candidate->id, [
        ['date' => '2026-08-03', 'period' => 'full_day'],
    ]);

    expect($result->all())->toBe([]);
});

test('unavailableDates excludes a date the candidate marked as fully available', function () {
    $candidate = EducationCandidate::factory()->create();

    CandidateAvailability::create([
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'date' => '2026-08-03',
        'status' => CandidateAvailabilityStatus::Available,
    ]);

    $result = BookingEligibility::unavailableDates(EducationCandidate::class, $candidate->id, [
        ['date' => '2026-08-03', 'period' => 'full_day'],
    ]);

    expect($result->all())->toBe([]);
});
