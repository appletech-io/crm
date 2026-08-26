<?php

use App\Models\Company;
use App\Models\EducationApplication;
use App\Models\EducationCandidate;
use App\Models\HealthcareApplication;
use App\Models\HealthcareCandidate;
use App\Services\PublicApplicationCompanyResolver;

test('it returns null for a blank token', function () {
    expect(PublicApplicationCompanyResolver::forToken(null))->toBeNull()
        ->and(PublicApplicationCompanyResolver::forToken(''))->toBeNull();
});

test('it returns null for a token that matches nothing', function () {
    expect(PublicApplicationCompanyResolver::forToken('unknown-token'))->toBeNull();
});

test('it resolves the company from an education application token', function () {
    $company = Company::factory()->create();
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);
    $application = EducationApplication::factory()->create([
        'education_candidate_id' => $candidate->id,
        'token' => 'edu-token',
    ]);

    $resolved = PublicApplicationCompanyResolver::forToken($application->token);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($company->id);
});

test('it resolves the company from a healthcare application token', function () {
    $company = Company::factory()->create();
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $company->id]);
    $application = HealthcareApplication::create([
        'candidate_type' => HealthcareCandidate::class,
        'candidate_id' => $candidate->id,
        'email' => 'candidate@example.com',
        'token' => 'healthcare-token',
        'status' => 'pending',
    ]);

    $resolved = PublicApplicationCompanyResolver::forToken($application->token);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($company->id);
});

test('it resolves the company from a reference token', function () {
    $company = Company::factory()->create();
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);
    $reference = $candidate->references()->create([
        'type' => 'professional',
        'first_name' => 'Ref',
        'last_name' => 'Eree',
        'email' => 'referee@example.com',
        'consent_to_contact' => true,
        'contact_now' => true,
        'status' => 'contacted',
        'token' => 'reference-token',
        'expires_on' => now()->addDays(7),
    ]);

    $resolved = PublicApplicationCompanyResolver::forToken($reference->token);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($company->id);
});
