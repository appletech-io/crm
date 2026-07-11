<?php

use App\Enums\DocumentType;
use App\Models\CandidateCandidateStatus;
use App\Models\CandidateDocument;
use App\Models\CandidateSkill;
use App\Models\CandidateStatus;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Services\CandidateVettingRequirements;

function fullyCompliantCandidate(array $attributes = []): EducationCandidate
{
    $company = Company::factory()->create();
    $industry = Industry::factory()->create(['slug' => 'education']);

    $candidate = EducationCandidate::factory()->create(array_merge([
        'company_id' => $company->id,
        'has_dbs' => 'yes',
        'dbs_certificate_number' => '001234567890',
        'barred_list_check' => 'yes',
        'lived_overseas_six_months' => 'no',
        'overseas_police_clearance_check' => null,
        'proof_of_address_match' => 'yes',
        'ni_number_match' => 'yes',
        'trn_number' => null,
        'trn_issue_date' => null,
        'safeguarding_certified_date' => now(),
        'prevent_training_completed' => 'yes',
        'right_to_work_type' => 'birth_certificate',
        'ni_number' => 'QQ123456C',
    ], $attributes));

    $skill = CandidateSkill::factory()->create([
        'company_id' => $company->id,
        'industry_id' => $industry->id,
    ]);
    $candidate->skills()->attach($skill);

    $status = CandidateStatus::factory()->create([
        'company_id' => $company->id,
        'industry_id' => $industry->id,
        'name' => 'Vetting',
    ]);
    CandidateCandidateStatus::create([
        'model_type' => EducationCandidate::class,
        'model_id' => $candidate->id,
        'candidate_status_id' => $status->id,
    ]);

    if ($candidate->right_to_work_type === 'birth_certificate') {
        CandidateDocument::create([
            'candidate_type' => EducationCandidate::class,
            'candidate_id' => $candidate->id,
            'document_type' => DocumentType::BirthCertificate,
            'path' => 'fake/birth-certificate.pdf',
        ]);
    }

    CandidateDocument::create([
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => DocumentType::Cv,
        'path' => 'fake/cv.pdf',
    ]);

    CandidateDocument::create([
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => DocumentType::Photo,
        'path' => 'fake/photo.jpg',
    ]);

    CandidateDocument::create([
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => DocumentType::DbsFront,
        'path' => 'fake/dbs-front.pdf',
    ]);

    CandidateDocument::create([
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => DocumentType::DbsBack,
        'path' => 'fake/dbs-back.pdf',
    ]);

    return $candidate->fresh();
}

test('isComplete is true when every check passes', function () {
    $candidate = fullyCompliantCandidate();

    expect(CandidateVettingRequirements::isComplete($candidate))->toBeTrue();
});

test('dbs check fails without a certificate number', function () {
    $candidate = fullyCompliantCandidate(['dbs_certificate_number' => null]);

    $checks = CandidateVettingRequirements::for($candidate);

    expect($checks['dbs']['complete'])->toBeFalse();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeFalse();
});

test('dbs check fails when the front of the certificate has not been uploaded', function () {
    $candidate = fullyCompliantCandidate();
    $candidate->documents()->where('document_type', DocumentType::DbsFront)->delete();

    $checks = CandidateVettingRequirements::for($candidate);

    expect($checks['dbs']['complete'])->toBeFalse();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeFalse();
});

test('dbs check fails when the back of the certificate has not been uploaded', function () {
    $candidate = fullyCompliantCandidate();
    $candidate->documents()->where('document_type', DocumentType::DbsBack)->delete();

    $checks = CandidateVettingRequirements::for($candidate);

    expect($checks['dbs']['complete'])->toBeFalse();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeFalse();
});

test('dbs check passes without any documents uploaded when the update service has verified it', function () {
    $candidate = fullyCompliantCandidate(['update_service_response' => 'Certificate is up to date.']);
    $candidate->documents()->where('document_type', DocumentType::DbsFront)->delete();
    $candidate->documents()->where('document_type', DocumentType::DbsBack)->delete();

    $checks = CandidateVettingRequirements::for($candidate);

    expect($checks['dbs']['complete'])->toBeTrue();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeTrue();
});

test('dbs check fails when the update service has not verified it and no documents are uploaded', function () {
    $candidate = fullyCompliantCandidate();
    $candidate->documents()->where('document_type', DocumentType::DbsFront)->delete();
    $candidate->documents()->where('document_type', DocumentType::DbsBack)->delete();

    $checks = CandidateVettingRequirements::for($candidate);

    expect($checks['dbs']['complete'])->toBeFalse();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeFalse();
});

test('dbs check passes even when has_dbs is no, as long as the certificate number and both documents are present', function () {
    $candidate = fullyCompliantCandidate(['has_dbs' => 'no']);

    $checks = CandidateVettingRequirements::for($candidate);

    expect($checks['dbs']['complete'])->toBeTrue();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeTrue();
});

test('skills check fails when no skills are recorded', function () {
    $candidate = fullyCompliantCandidate();
    $candidate->skills()->detach();

    $checks = CandidateVettingRequirements::for($candidate);

    expect($checks['skills']['complete'])->toBeFalse();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeFalse();
});

test('not barred check fails unless the barred list check is cleared', function () {
    $candidate = fullyCompliantCandidate(['barred_list_check' => 'no']);

    $checks = CandidateVettingRequirements::for($candidate);

    expect($checks['not_barred']['complete'])->toBeFalse();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeFalse();
});

test('overseas clearance check is complete when the candidate never lived overseas', function () {
    $candidate = fullyCompliantCandidate([
        'lived_overseas_six_months' => 'no',
        'overseas_police_clearance_check' => null,
    ]);

    expect(CandidateVettingRequirements::for($candidate)['overseas_clearance']['complete'])->toBeTrue();
});

test('overseas clearance check fails when applicable and not cleared', function () {
    $candidate = fullyCompliantCandidate([
        'lived_overseas_six_months' => 'yes',
        'overseas_police_clearance_check' => 'no',
    ]);

    expect(CandidateVettingRequirements::for($candidate)['overseas_clearance']['complete'])->toBeFalse();
});

test('overseas clearance check passes when applicable and cleared', function () {
    $candidate = fullyCompliantCandidate([
        'lived_overseas_six_months' => 'yes',
        'overseas_police_clearance_check' => 'yes',
    ]);

    expect(CandidateVettingRequirements::for($candidate)['overseas_clearance']['complete'])->toBeTrue();
});

test('proof of address check fails when it does not match', function () {
    $candidate = fullyCompliantCandidate(['proof_of_address_match' => 'no']);

    $checks = CandidateVettingRequirements::for($candidate);

    expect($checks['proof_of_address']['complete'])->toBeFalse();
});

test('proof of NI check fails when it does not match', function () {
    $candidate = fullyCompliantCandidate(['ni_number_match' => 'no']);

    $checks = CandidateVettingRequirements::for($candidate);

    expect($checks['proof_of_ni']['complete'])->toBeFalse();
});

test('trn check passes when no trn is required', function () {
    $candidate = fullyCompliantCandidate(['trn_number' => null, 'trn_issue_date' => null]);

    expect(CandidateVettingRequirements::for($candidate)['trn']['complete'])->toBeTrue();
});

test('trn check fails when a trn is set without an issue date', function () {
    $candidate = fullyCompliantCandidate(['trn_number' => '1234567', 'trn_issue_date' => null]);

    expect(CandidateVettingRequirements::for($candidate)['trn']['complete'])->toBeFalse();
});

test('trn check passes when a trn has an issue date', function () {
    $candidate = fullyCompliantCandidate(['trn_number' => '1234567', 'trn_issue_date' => now()]);

    expect(CandidateVettingRequirements::for($candidate)['trn']['complete'])->toBeTrue();
});

test('safeguarding check fails without a certified date', function () {
    $candidate = fullyCompliantCandidate(['safeguarding_certified_date' => null]);

    expect(CandidateVettingRequirements::for($candidate)['safeguarding']['complete'])->toBeFalse();
});

test('prevent training check fails unless completed is yes', function () {
    $candidate = fullyCompliantCandidate(['prevent_training_completed' => 'no']);

    expect(CandidateVettingRequirements::for($candidate)['prevent_training']['complete'])->toBeFalse();
});

test('right to work check fails without a type set', function () {
    $candidate = fullyCompliantCandidate(['right_to_work_type' => null]);

    expect(CandidateVettingRequirements::for($candidate)['right_to_work']['complete'])->toBeFalse();
});

test('right to work is complete for visa only once share code and dates are set', function () {
    $candidate = fullyCompliantCandidate([
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

test('right to work is complete for passport only once the document is uploaded', function () {
    $candidate = fullyCompliantCandidate(['right_to_work_type' => 'passport']);

    expect(CandidateVettingRequirements::for($candidate)['right_to_work']['complete'])->toBeFalse();

    CandidateDocument::create([
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => DocumentType::Passport,
        'path' => 'fake/passport.pdf',
    ]);

    expect(CandidateVettingRequirements::for($candidate)['right_to_work']['complete'])->toBeTrue();
});

test('right to work is complete for birth certificate only once the document and NI number are set', function () {
    $candidate = fullyCompliantCandidate([
        'right_to_work_type' => 'birth_certificate',
        'ni_number' => null,
    ]);

    expect(CandidateVettingRequirements::for($candidate)['right_to_work']['complete'])->toBeFalse();

    $candidate->update(['ni_number' => 'QQ123456C']);

    expect(CandidateVettingRequirements::for($candidate)['right_to_work']['complete'])->toBeTrue();

    $candidate->documents()->delete();

    expect(CandidateVettingRequirements::for($candidate)['right_to_work']['complete'])->toBeFalse();
});

test('cv check fails unless a CV document is uploaded', function () {
    $candidate = fullyCompliantCandidate();
    $candidate->documents()->where('document_type', DocumentType::Cv)->delete();

    expect(CandidateVettingRequirements::for($candidate)['cv']['complete'])->toBeFalse();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeFalse();
});

test('headshot photo check fails unless a photo document is uploaded', function () {
    $candidate = fullyCompliantCandidate();
    $candidate->documents()->where('document_type', DocumentType::Photo)->delete();

    expect(CandidateVettingRequirements::for($candidate)['headshot_photo']['complete'])->toBeFalse();
    expect(CandidateVettingRequirements::isComplete($candidate))->toBeFalse();
});

test('right to work label includes the mode in brackets', function () {
    $candidate = fullyCompliantCandidate(['right_to_work_type' => 'passport']);
    expect(CandidateVettingRequirements::for($candidate)['right_to_work']['label'])->toBe('Right to Work (UK Passport)');

    $candidate->update(['right_to_work_type' => 'visa']);
    expect(CandidateVettingRequirements::for($candidate)['right_to_work']['label'])->toBe('Right to Work (Visa)');

    $candidate->update(['right_to_work_type' => 'birth_certificate']);
    expect(CandidateVettingRequirements::for($candidate)['right_to_work']['label'])->toBe('Right to Work (UK Birth Certificate)');

    $candidate->update(['right_to_work_type' => null]);
    expect(CandidateVettingRequirements::for($candidate)['right_to_work']['label'])->toBe('Right to Work');
});

test('checks no longer include a standalone ni number or dnu entry', function () {
    $candidate = fullyCompliantCandidate();

    $checks = CandidateVettingRequirements::for($candidate);

    expect($checks)->not->toHaveKey('ni_number');
    expect($checks)->not->toHaveKey('dnu');
    expect($checks)->not->toHaveKey('visa');
    expect($checks)->not->toHaveKey('passport');
    expect($checks)->not->toHaveKey('birth_certificate');
});
