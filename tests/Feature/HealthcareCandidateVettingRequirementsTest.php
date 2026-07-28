<?php

use App\Enums\DocumentType;
use App\Models\CandidateDocument;
use App\Models\CandidateSkill;
use App\Models\Company;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\PayRate;
use App\Services\Healthcare\CandidateVettingRequirements;
use App\Services\Healthcare\DbsUpdateService;

function fullyCompliantHealthcareCandidate(array $attributes = []): HealthcareCandidate
{
    $company = Company::factory()->create();
    $industry = Industry::factory()->create(['slug' => 'healthcare']);

    $candidate = HealthcareCandidate::factory()->create(array_merge([
        'company_id' => $company->id,
        'dbs_certificate_number' => '001234567890',
        'proof_of_address_match' => 'yes',
        'ni_number_match' => 'yes',
        'professional_registration_body' => 'NMC',
        'professional_registration_number' => '12345678',
        'professional_registration_checked_at' => now(),
        'right_to_work_type' => 'birth_certificate',
        'ni_number' => 'QQ123456C',
    ], $attributes));

    $skill = CandidateSkill::factory()->create([
        'company_id' => $company->id,
        'industry_id' => $industry->id,
    ]);
    $candidate->skills()->attach($skill);

    $jobTitle = JobTitle::factory()->create([
        'company_id' => $company->id,
        'industry_id' => $industry->id,
    ]);
    PayRate::create([
        'company_id' => $company->id,
        'model_type' => HealthcareCandidate::class,
        'model_id' => $candidate->id,
        'job_title_id' => $jobTitle->id,
        'hourly_rate' => 20,
    ]);

    if ($candidate->right_to_work_type === 'birth_certificate') {
        CandidateDocument::create([
            'candidate_type' => HealthcareCandidate::class,
            'candidate_id' => $candidate->id,
            'document_type' => DocumentType::BirthCertificate,
            'path' => 'fake/birth-certificate.pdf',
        ]);
    }

    CandidateDocument::create([
        'candidate_type' => HealthcareCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => DocumentType::Cv,
        'path' => 'fake/cv.pdf',
    ]);

    CandidateDocument::create([
        'candidate_type' => HealthcareCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => DocumentType::Photo,
        'path' => 'fake/photo.jpg',
    ]);

    CandidateDocument::create([
        'candidate_type' => HealthcareCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => DocumentType::DbsFront,
        'path' => 'fake/dbs-front.pdf',
    ]);

    CandidateDocument::create([
        'candidate_type' => HealthcareCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => DocumentType::DbsBack,
        'path' => 'fake/dbs-back.pdf',
    ]);

    CandidateDocument::create([
        'candidate_type' => HealthcareCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => DocumentType::Reference,
        'path' => 'fake/reference.pdf',
    ]);

    return $candidate->fresh();
}

test('isComplete is true when every check passes', function () {
    $candidate = fullyCompliantHealthcareCandidate();

    expect(CandidateVettingRequirements::isComplete($candidate))->toBeTrue();
});

test('dbs check passes without any documents uploaded when the update service has verified it', function () {
    $candidate = fullyCompliantHealthcareCandidate(['update_service_response' => DbsUpdateService::VALID_STATUS]);
    $candidate->documents()->where('document_type', DocumentType::DbsFront)->delete();
    $candidate->documents()->where('document_type', DocumentType::DbsBack)->delete();

    expect(CandidateVettingRequirements::for($candidate)['dbs']['complete'])->toBeTrue();
});

test('professional registration check fails unless body, number, and checked date are all set', function () {
    $candidate = fullyCompliantHealthcareCandidate(['professional_registration_checked_at' => null]);

    expect(CandidateVettingRequirements::for($candidate)['professional_registration']['complete'])->toBeFalse();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeFalse();
});

test('reference check fails when there is neither a confirmed reference nor a reference document', function () {
    $candidate = fullyCompliantHealthcareCandidate();
    $candidate->documents()->where('document_type', DocumentType::Reference)->delete();

    expect(CandidateVettingRequirements::for($candidate)['reference']['complete'])->toBeFalse();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeFalse();
});

test('reference check passes with a reference document even without a confirmed reference', function () {
    $candidate = fullyCompliantHealthcareCandidate();

    expect(CandidateVettingRequirements::for($candidate)['reference']['complete'])->toBeTrue();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeTrue();
});

test('reference check passes with a confirmed reference even without a reference document', function () {
    $candidate = fullyCompliantHealthcareCandidate();
    $candidate->documents()->where('document_type', DocumentType::Reference)->delete();
    $candidate->references()->create(['type' => 'professional', 'first_name' => 'Jane', 'last_name' => 'Doe', 'status' => 'confirmed']);

    expect(CandidateVettingRequirements::for($candidate)['reference']['complete'])->toBeTrue();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeTrue();
});

test('reference check fails when a reference exists but is not confirmed', function () {
    $candidate = fullyCompliantHealthcareCandidate();
    $candidate->documents()->where('document_type', DocumentType::Reference)->delete();
    $candidate->references()->create(['type' => 'professional', 'first_name' => 'Jane', 'last_name' => 'Doe', 'status' => 'pending']);

    expect(CandidateVettingRequirements::for($candidate)['reference']['complete'])->toBeFalse();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeFalse();
});
