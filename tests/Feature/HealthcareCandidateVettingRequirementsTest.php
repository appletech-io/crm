<?php

use App\Enums\DocumentType;
use App\Enums\PaymentMethod;
use App\Models\CandidateDocument;
use App\Models\CandidateSkill;
use App\Models\Company;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\PaymentProvider;
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
        'reference_checked' => 'yes',
        'professional_registration_body' => 'NMC',
        'professional_registration_number' => '12345678',
        'professional_registration_checked_at' => now(),
        'right_to_work_type' => 'birth_certificate',
        'ni_number' => 'QQ123456C',
        'payment_method' => PaymentMethod::Paye,
        'bank_account_number' => '12345678',
        'bank_sort_code' => '123456',
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

test('payment method check fails when no payment method has been set', function () {
    $candidate = fullyCompliantHealthcareCandidate(['payment_method' => null]);

    $checks = CandidateVettingRequirements::for($candidate);

    expect($checks['payment_method']['complete'])->toBeFalse();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeFalse();
});

test('payment method check fails for PAYE without bank account number or sort code', function () {
    $candidate = fullyCompliantHealthcareCandidate(['payment_method' => PaymentMethod::Paye, 'bank_account_number' => null, 'bank_sort_code' => null]);

    $checks = CandidateVettingRequirements::for($candidate);

    expect($checks['payment_method']['complete'])->toBeFalse();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeFalse();
});

test('payment method check fails for umbrella without a payment provider selected', function () {
    $candidate = fullyCompliantHealthcareCandidate(['payment_method' => PaymentMethod::Umbrella, 'payment_provider_id' => null]);

    $checks = CandidateVettingRequirements::for($candidate);

    expect($checks['payment_method']['complete'])->toBeFalse();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeFalse();
});

test('payment method check passes for umbrella with a payment provider selected', function () {
    $candidate = fullyCompliantHealthcareCandidate();
    $paymentProvider = PaymentProvider::factory()->create(['company_id' => $candidate->company_id]);
    $candidate->update(['payment_method' => PaymentMethod::Umbrella, 'payment_provider_id' => $paymentProvider->id]);

    $checks = CandidateVettingRequirements::for($candidate->fresh());

    expect($checks['payment_method']['complete'])->toBeTrue();
});

test('dbs check passes without any documents uploaded when the update service has verified it', function () {
    $candidate = fullyCompliantHealthcareCandidate(['update_service_response' => DbsUpdateService::VALID_STATUS]);
    $candidate->documents()->where('document_type', DocumentType::DbsFront)->delete();
    $candidate->documents()->where('document_type', DocumentType::DbsBack)->delete();

    expect(CandidateVettingRequirements::for($candidate)['dbs']['complete'])->toBeTrue();
});

test('right to work is complete for visa only once share code and dates are set', function () {
    $candidate = fullyCompliantHealthcareCandidate([
        'right_to_work_type' => 'visa',
        'visa_share_code' => null,
    ]);

    expect(CandidateVettingRequirements::for($candidate)['right_to_work']['complete'])->toBeFalse();

    $candidate->update([
        'visa_share_code' => 'ABC123',
        'visa_issue_date' => now(),
        'visa_expiry_date' => now()->addYear(),
    ]);

    expect(CandidateVettingRequirements::for($candidate)['right_to_work']['complete'])->toBeTrue();
});

test('dbs check fails when the certificate has already expired or expires within 14 days', function () {
    $candidate = fullyCompliantHealthcareCandidate(['dbs_expiry_date' => now()->addDays(15)]);
    expect(CandidateVettingRequirements::for($candidate)['dbs']['complete'])->toBeTrue();

    $candidate->update(['dbs_expiry_date' => now()->addDays(14)]);
    expect(CandidateVettingRequirements::for($candidate)['dbs']['complete'])->toBeFalse();

    $candidate->update(['dbs_expiry_date' => now()->subDay()]);
    expect(CandidateVettingRequirements::for($candidate)['dbs']['complete'])->toBeFalse();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeFalse();
});

test('right to work fails for a passport once the right to work expiry date has passed or is within 14 days', function () {
    $candidate = fullyCompliantHealthcareCandidate(['right_to_work_type' => 'passport']);

    CandidateDocument::create([
        'candidate_type' => HealthcareCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => DocumentType::Passport,
        'path' => 'fake/passport.pdf',
    ]);

    $candidate->update(['right_to_work_expiry_date' => now()->addDays(15)]);
    expect(CandidateVettingRequirements::for($candidate)['right_to_work']['complete'])->toBeTrue();

    $candidate->update(['right_to_work_expiry_date' => now()->subDay()]);
    expect(CandidateVettingRequirements::for($candidate)['right_to_work']['complete'])->toBeFalse();
});

test('right to work for a birth certificate is unaffected by the right to work expiry date field', function () {
    $candidate = fullyCompliantHealthcareCandidate([
        'right_to_work_type' => 'birth_certificate',
        'right_to_work_expiry_date' => now()->subDay(),
    ]);

    expect(CandidateVettingRequirements::for($candidate)['right_to_work']['complete'])->toBeTrue();
});

test('visa restrictions checked is only relevant for visa candidates and requires a manual confirmation', function () {
    $candidate = fullyCompliantHealthcareCandidate(['right_to_work_type' => 'passport']);

    // Not a visa candidate — vacuously complete, nothing to check.
    expect(CandidateVettingRequirements::for($candidate)['visa_restrictions_checked']['complete'])->toBeTrue();

    $candidate->update([
        'right_to_work_type' => 'visa',
        'visa_share_code' => 'ABC123',
        'visa_issue_date' => now(),
        'visa_expiry_date' => now()->addYear(),
    ]);

    // Visa details being set doesn't imply restrictions have been checked —
    // this is a distinct, manual-only confirmation.
    expect(CandidateVettingRequirements::for($candidate)['visa_restrictions_checked']['complete'])->toBeFalse();

    $candidate->update(['right_to_work_checked' => 'no']);
    expect(CandidateVettingRequirements::for($candidate)['visa_restrictions_checked']['complete'])->toBeFalse();

    $candidate->update(['right_to_work_checked' => 'yes']);
    expect(CandidateVettingRequirements::for($candidate)['visa_restrictions_checked']['complete'])->toBeTrue();
});

test('professional registration check fails unless body, number, and checked date are all set', function () {
    $candidate = fullyCompliantHealthcareCandidate(['professional_registration_checked_at' => null]);

    expect(CandidateVettingRequirements::for($candidate)['professional_registration']['complete'])->toBeFalse();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeFalse();
});

test('overseas clearance check is complete when the candidate never lived overseas', function () {
    $candidate = fullyCompliantHealthcareCandidate([
        'lived_overseas_six_months' => 'no',
        'overseas_police_clearance_check' => null,
    ]);

    expect(CandidateVettingRequirements::for($candidate)['overseas_clearance']['complete'])->toBeTrue();
});

test('overseas clearance check fails when applicable and not cleared', function () {
    $candidate = fullyCompliantHealthcareCandidate([
        'lived_overseas_six_months' => 'yes',
        'overseas_police_clearance_check' => 'no',
    ]);

    expect(CandidateVettingRequirements::for($candidate)['overseas_clearance']['complete'])->toBeFalse();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeFalse();
});

test('overseas clearance check passes when applicable and cleared', function () {
    $candidate = fullyCompliantHealthcareCandidate([
        'lived_overseas_six_months' => 'yes',
        'overseas_police_clearance_check' => 'yes',
    ]);

    expect(CandidateVettingRequirements::for($candidate)['overseas_clearance']['complete'])->toBeTrue();
});

test('reference check fails when not manually confirmed, even with a confirmed reference', function () {
    $candidate = fullyCompliantHealthcareCandidate(['reference_checked' => null]);
    $candidate->documents()->where('document_type', DocumentType::Reference)->delete();
    $candidate->references()->create(['type' => 'professional', 'first_name' => 'Jane', 'last_name' => 'Doe', 'status' => 'confirmed']);

    expect(CandidateVettingRequirements::for($candidate)['reference']['complete'])->toBeFalse();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeFalse();
});

test('reference check fails when not manually confirmed, even with a reference document uploaded', function () {
    $candidate = fullyCompliantHealthcareCandidate(['reference_checked' => null]);

    expect(CandidateVettingRequirements::for($candidate)['reference']['complete'])->toBeFalse();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeFalse();
});

test('reference check passes once manually confirmed, even without any references or documents', function () {
    $candidate = fullyCompliantHealthcareCandidate(['reference_checked' => 'yes']);
    $candidate->documents()->where('document_type', DocumentType::Reference)->delete();

    expect(CandidateVettingRequirements::for($candidate)['reference']['complete'])->toBeTrue();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeTrue();
});
