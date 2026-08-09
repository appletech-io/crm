<?php

use App\Jobs\SendApplicationEmail;
use App\Models\Company;
use App\Models\EducationApplication;
use App\Models\EducationCandidate;
use App\Models\EmailTemplate;
use App\Models\HealthcareApplication;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->company = Company::factory()->create([
        'ms_tenant_id' => 'tenant',
        'ms_client_id' => 'client',
        'ms_client_secret' => 'secret',
        'ms_sender_email' => 'sender@example.com',
    ]);

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/*' => Http::response([], 202),
    ]);
});

describe('education candidates', function () {
    beforeEach(function () {
        $industry = Industry::factory()->create(['name' => 'Education', 'slug' => 'education']);

        EmailTemplate::create([
            'company_id' => $this->company->id,
            'industry_id' => $industry->id,
            'name' => 'Application',
            'type' => 'application',
            'subject' => 'Apply now, {firstname}',
            'body' => 'Hi {firstname}, please apply: {application_link}',
        ]);

        $this->candidate = EducationCandidate::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Jane',
        ]);

        $this->application = EducationApplication::factory()->create([
            'education_candidate_id' => $this->candidate->id,
        ]);
    });

    test('it sends from the candidates consultant when one is assigned, ignoring the creator', function () {
        $consultant = User::factory()->create(['company_id' => $this->company->id, 'email' => 'consultant@example.com']);
        $creator = User::factory()->create(['company_id' => $this->company->id, 'email' => 'creator@example.com']);

        $this->candidate->update(['consultant_id' => $consultant->id]);

        (new SendApplicationEmail($this->candidate, $this->application, $creator->id))->handle();

        Http::assertSent(fn ($request) => str_contains($request->url(), "users/{$consultant->email}/sendMail"));
    });

    test('it falls back to the creator when the candidate has no consultant yet', function () {
        $creator = User::factory()->create(['company_id' => $this->company->id, 'email' => 'creator@example.com']);

        (new SendApplicationEmail($this->candidate, $this->application, $creator->id))->handle();

        Http::assertSent(fn ($request) => str_contains($request->url(), "users/{$creator->email}/sendMail"));
    });

    test('it falls back to the company default sender when there is no consultant and no creator', function () {
        (new SendApplicationEmail($this->candidate, $this->application, null))->handle();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'users/sender@example.com/sendMail'));
    });

    test('it sends from the compliance officer when the application template requests it', function () {
        EmailTemplate::query()->update(['sender' => 'compliance_officer']);

        $complianceOfficer = User::factory()->create(['company_id' => $this->company->id, 'email' => 'compliance@example.com']);
        $consultant = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'consultant@example.com',
            'compliance_officer_id' => $complianceOfficer->id,
        ]);

        $this->candidate->update(['consultant_id' => $consultant->id]);

        (new SendApplicationEmail($this->candidate, $this->application, null))->handle();

        Http::assertSent(fn ($request) => str_contains($request->url(), "users/{$complianceOfficer->email}/sendMail"));
    });

    test('it logs the activity against the creator when the candidate has no consultant yet', function () {
        $creator = User::factory()->create(['company_id' => $this->company->id]);

        (new SendApplicationEmail($this->candidate, $this->application, $creator->id))->handle();

        expect($this->candidate->activities()->latest()->first()->user_id)->toBe($creator->id);
    });

    test('it links to the education application form', function () {
        (new SendApplicationEmail($this->candidate, $this->application, null))->handle();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.microsoft.com')
            && str_contains($request['message']['body']['content'], route('application.form', ['token' => $this->application->token])));
    });
});

describe('healthcare candidates', function () {
    beforeEach(function () {
        $industry = Industry::factory()->create(['name' => 'Healthcare', 'slug' => 'healthcare']);

        EmailTemplate::create([
            'company_id' => $this->company->id,
            'industry_id' => $industry->id,
            'name' => 'Application',
            'type' => 'application',
            'subject' => 'Apply now, {firstname}',
            'body' => 'Hi {firstname}, please apply: {application_link}',
        ]);

        $this->candidate = HealthcareCandidate::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Jane',
        ]);

        $this->application = HealthcareApplication::create([
            'candidate_type' => HealthcareCandidate::class,
            'candidate_id' => $this->candidate->id,
            'email' => $this->candidate->email,
            'status' => 'pending',
            'token' => Str::random(32),
            'expires_on' => now()->addDays(7),
        ]);
    });

    test('it sends from the candidates consultant when one is assigned, ignoring the creator', function () {
        $consultant = User::factory()->create(['company_id' => $this->company->id, 'email' => 'consultant@example.com']);
        $creator = User::factory()->create(['company_id' => $this->company->id, 'email' => 'creator@example.com']);

        $this->candidate->update(['consultant_id' => $consultant->id]);

        (new SendApplicationEmail($this->candidate, $this->application, $creator->id))->handle();

        Http::assertSent(fn ($request) => str_contains($request->url(), "users/{$consultant->email}/sendMail"));
    });

    test('it falls back to the creator when the candidate has no consultant yet', function () {
        $creator = User::factory()->create(['company_id' => $this->company->id, 'email' => 'creator@example.com']);

        (new SendApplicationEmail($this->candidate, $this->application, $creator->id))->handle();

        Http::assertSent(fn ($request) => str_contains($request->url(), "users/{$creator->email}/sendMail"));
    });

    test('it falls back to the company default sender when there is no consultant and no creator', function () {
        (new SendApplicationEmail($this->candidate, $this->application, null))->handle();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'users/sender@example.com/sendMail'));
    });

    test('it logs the activity against the creator when the candidate has no consultant yet', function () {
        $creator = User::factory()->create(['company_id' => $this->company->id]);

        (new SendApplicationEmail($this->candidate, $this->application, $creator->id))->handle();

        expect($this->candidate->activities()->latest()->first()->user_id)->toBe($creator->id);
    });

    test('it links to the healthcare application form', function () {
        (new SendApplicationEmail($this->candidate, $this->application, null))->handle();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.microsoft.com')
            && str_contains($request['message']['body']['content'], route('application.healthcare.form', ['token' => $this->application->token])));
    });
});
