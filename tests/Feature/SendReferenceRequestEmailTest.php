<?php

use App\Enums\ReferenceStatus;
use App\Jobs\SendReferenceRequestEmail;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\EmailTemplate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Illuminate\Support\Facades\Http;

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

function createReferenceRequestTemplate(Company $company, Industry $industry): void
{
    EmailTemplate::create([
        'company_id' => $company->id,
        'industry_id' => $industry->id,
        'name' => 'Reference Request',
        'type' => 'reference_request',
        'subject' => 'Reference for {candidate_name}',
        'body' => 'Hi {referee_name}, please complete this reference for {candidate_name}: {reference_link} (expires {expiry_date})',
    ]);
}

describe('education candidates', function () {
    beforeEach(function () {
        $this->industry = Industry::factory()->create(['name' => 'Education', 'slug' => 'education']);
        createReferenceRequestTemplate($this->company, $this->industry);

        $this->candidate = EducationCandidate::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $this->reference = $this->candidate->references()->create([
            'type' => 'professional',
            'first_name' => 'Ref',
            'last_name' => 'Eree',
            'email' => 'referee@example.com',
            'consent_to_contact' => true,
            'contact_now' => true,
            'status' => ReferenceStatus::Contacted,
            'token' => 'the-token',
            'expires_on' => now()->addDays(7),
        ]);
    });

    test('it does nothing when the reference has no email', function () {
        $this->reference->update(['email' => null]);

        (new SendReferenceRequestEmail($this->reference))->handle();

        Http::assertNothingSent();
    });

    test('it does nothing when contact_now is false, even if dispatched directly', function () {
        $this->reference->update(['contact_now' => false]);

        (new SendReferenceRequestEmail($this->reference))->handle();

        Http::assertNothingSent();
    });

    test('it does nothing when there is no reference_request template for the company and industry', function () {
        EmailTemplate::query()->delete();

        (new SendReferenceRequestEmail($this->reference))->handle();

        Http::assertNothingSent();
    });

    test('it sends from the candidates consultant', function () {
        $consultant = User::factory()->create(['company_id' => $this->company->id, 'email' => 'consultant@example.com']);
        $this->candidate->update(['consultant_id' => $consultant->id]);

        (new SendReferenceRequestEmail($this->reference))->handle();

        Http::assertSent(fn ($request) => str_contains($request->url(), "users/{$consultant->email}/sendMail"));
    });

    test('it falls back to the company default sender when there is no consultant', function () {
        (new SendReferenceRequestEmail($this->reference))->handle();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'users/sender@example.com/sendMail'));
    });

    test('it sends from the compliance officer when the template requests it, and logs the activity against them too', function () {
        EmailTemplate::query()->update(['sender' => 'compliance_officer']);

        $complianceOfficer = User::factory()->create(['company_id' => $this->company->id, 'email' => 'compliance@example.com']);
        $consultant = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'consultant@example.com',
            'compliance_officer_id' => $complianceOfficer->id,
        ]);
        $this->candidate->update(['consultant_id' => $consultant->id]);

        (new SendReferenceRequestEmail($this->reference))->handle();

        Http::assertSent(fn ($request) => str_contains($request->url(), "users/{$complianceOfficer->email}/sendMail"));

        $activity = $this->candidate->activities()->latest()->first();

        expect($activity->user_id)->toBe($complianceOfficer->id);
    });

    test('it logs the activity against the consultant when the template does not request the compliance officer', function () {
        $consultant = User::factory()->create(['company_id' => $this->company->id, 'email' => 'consultant@example.com']);
        $this->candidate->update(['consultant_id' => $consultant->id]);

        (new SendReferenceRequestEmail($this->reference))->handle();

        $activity = $this->candidate->activities()->latest()->first();

        expect($activity->user_id)->toBe($consultant->id);
    });

    test('it replaces the referee name, candidate name, reference link, and expiry date placeholders', function () {
        (new SendReferenceRequestEmail($this->reference))->handle();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'graph.microsoft.com')) {
                return false;
            }

            $content = $request['message']['body']['content'];

            return str_contains($content, 'Hi Ref Eree')
                && str_contains($content, 'for Jane Doe')
                && str_contains($content, route('reference.verify', ['token' => 'the-token']))
                && str_contains($content, $this->reference->expires_on->format('d M Y'));
        });
    });

    test('it logs the activity against the candidate', function () {
        (new SendReferenceRequestEmail($this->reference))->handle();

        $activity = $this->candidate->activities()->latest()->first();

        expect($activity->note)->toBe('Reference request sent');
    });
});

describe('healthcare candidates', function () {
    beforeEach(function () {
        $this->industry = Industry::factory()->create(['name' => 'Healthcare', 'slug' => 'healthcare']);
        createReferenceRequestTemplate($this->company, $this->industry);

        $this->candidate = HealthcareCandidate::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $this->reference = $this->candidate->references()->create([
            'type' => 'agency',
            'first_name' => 'Ref',
            'last_name' => 'Eree',
            'email' => 'referee@example.com',
            'consent_to_contact' => true,
            'contact_now' => true,
            'status' => ReferenceStatus::Contacted,
            'token' => 'the-token',
            'expires_on' => now()->addDays(7),
        ]);
    });

    test('it links to the reference form for a healthcare candidate too', function () {
        (new SendReferenceRequestEmail($this->reference))->handle();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.microsoft.com')
            && str_contains($request['message']['body']['content'], route('reference.verify', ['token' => 'the-token'])));
    });
});
