<?php

use App\Exceptions\Dbs\MissingCandidateDetailsException;
use App\Exceptions\Dbs\MissingCertificateNumberException;
use App\Exceptions\Dbs\MissingCompanyLegalNameException;
use App\Exceptions\Dbs\UpdateServiceCheckRejectedException;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\User;
use App\Services\DbsUpdateService;
use Illuminate\Support\Facades\Http;

test('check stores the status returned by the update service on the candidate', function () {
    $user = User::factory()->create(['name' => 'Jane Smith']);
    $this->actingAs($user);

    $company = Company::factory()->create(['legal_name' => 'Applebough Ltd']);

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $company->id,
        'first_name' => 'Billy',
        'last_name' => 'Jones',
        'date_of_birth' => '1990-05-15',
        'dbs_certificate_number' => '001234567890',
    ]);

    Http::fake([
        'secure.crbonline.gov.uk/*' => Http::response(<<<'XML'
            <statusCheckResult>
                <statusCheckResultType>SUCCESS</statusCheckResultType>
                <status>BLANK_NO_NEW_INFO</status>
                <forename>BILLY</forename>
                <surname>JONES</surname>
                <printDate class="sql-date">2013-06-10</printDate>
            </statusCheckResult>
            XML),
    ]);

    $status = (new DbsUpdateService)->check($candidate);

    expect($status)->toBe('BLANK_NO_NEW_INFO');
    expect($candidate->refresh()->update_service_response)->toBe('BLANK_NO_NEW_INFO');

    Http::assertSent(function ($request) {
        return str($request->url())->contains('secure.crbonline.gov.uk/crsc/api/status/001234567890')
            && $request['dateOfBirth'] === '15/05/1990'
            && $request['surname'] === 'Jones'
            && $request['hasAgreedTermsAndConditions'] === 'true'
            && $request['organisationName'] === 'Applebough Ltd'
            && $request['employeeForename'] === 'Billy'
            && $request['employeeSurname'] === 'Jones';
    });
});

test('check throws when the candidate has no first name', function () {
    $candidate = EducationCandidate::factory()->create([
        'first_name' => null,
        'last_name' => 'Jones',
        'date_of_birth' => '1990-05-15',
        'dbs_certificate_number' => '001234567890',
    ]);

    try {
        (new DbsUpdateService)->check($candidate);
    } catch (MissingCandidateDetailsException $exception) {
        expect($exception->getMessage())->toBe('Candidate is missing details required for a DBS Update Service check.');

        return;
    }

    $this->fail('Expected MissingCandidateDetailsException to be thrown.');
});

test('check throws when the candidate has no dbs certificate number', function () {
    $candidate = EducationCandidate::factory()->create([
        'last_name' => 'Jones',
        'date_of_birth' => '1990-05-15',
        'dbs_certificate_number' => null,
    ]);

    try {
        (new DbsUpdateService)->check($candidate);
    } catch (MissingCertificateNumberException $exception) {
        expect($exception->getMessage())->toBe('Candidate does not have a DBS certificate number to check.');
        expect($exception->context())->toBe(['candidate_id' => $candidate->id]);

        return;
    }

    $this->fail('Expected MissingCertificateNumberException to be thrown.');
});

test('check throws when the candidate\'s company has no legal name', function () {
    $company = Company::factory()->create(['legal_name' => null]);

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $company->id,
        'last_name' => 'Jones',
        'date_of_birth' => '1990-05-15',
        'dbs_certificate_number' => '001234567890',
    ]);

    try {
        (new DbsUpdateService)->check($candidate);
    } catch (MissingCompanyLegalNameException $exception) {
        expect($exception->getMessage())->toBe('Candidate\'s company has no legal name set for a DBS Update Service check.');
        expect($exception->context())->toBe([
            'candidate_id' => $candidate->id,
            'company_id' => $company->id,
        ]);

        return;
    }

    $this->fail('Expected MissingCompanyLegalNameException to be thrown.');
});

test('check throws when the update service reports an unsuccessful result', function () {
    $company = Company::factory()->create(['legal_name' => 'Applebough Ltd']);

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $company->id,
        'last_name' => 'Jones',
        'date_of_birth' => '1990-05-15',
        'dbs_certificate_number' => '001234567890',
    ]);

    Http::fake([
        'secure.crbonline.gov.uk/*' => Http::response(<<<'XML'
            <statusCheckResult>
                <statusCheckResultType>REJECTED</statusCheckResultType>
                <status></status>
            </statusCheckResult>
            XML),
    ]);

    try {
        (new DbsUpdateService)->check($candidate);
    } catch (UpdateServiceCheckRejectedException $exception) {
        expect($exception->resultType)->toBe('REJECTED');
        expect($exception->context())->toBe([
            'candidate_id' => $candidate->id,
            'dbs_certificate_number' => '001234567890',
            'result_type' => 'REJECTED',
        ]);

        return;
    }

    $this->fail('Expected UpdateServiceCheckRejectedException to be thrown.');
});
