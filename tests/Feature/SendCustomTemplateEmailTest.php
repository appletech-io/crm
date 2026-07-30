<?php

use App\Enums\EmailTemplateAudience;
use App\Enums\EmailTemplateType;
use App\Jobs\SendCustomTemplateEmail;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\EmailTemplate;
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
    $this->industry = Industry::factory()->create(['slug' => 'education']);

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/*' => Http::response([], 202),
    ]);
});

test('it sends a custom template to a candidate with resolved placeholders', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id, 'email' => 'consultant@example.com']);
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'consultant_id' => $consultant->id,
    ]);

    $template = EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Custom candidate template',
        'type' => EmailTemplateType::Custom->value,
        'audience' => EmailTemplateAudience::Candidate->value,
        'subject' => 'Hello {recipient_first_name}',
        'body' => 'Dear {recipient_name}, regards {consultant_name}',
    ]);

    (new SendCustomTemplateEmail($template, $candidate, $consultant->id))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), "users/{$consultant->email}/sendMail")
        && str_contains($request['message']['subject'], 'Hello Jane')
        && str_contains($request['message']['body']['content'], 'Dear Jane Doe, regards '.$consultant->name));

    expect($candidate->activities()->latest()->first()->user_id)->toBe($consultant->id);
});

test('it does not send when a candidate has no email', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'email' => null]);

    $template = EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Custom candidate template',
        'type' => EmailTemplateType::Custom->value,
        'audience' => EmailTemplateAudience::Candidate->value,
        'subject' => 'Hello',
        'body' => 'Hi',
    ]);

    (new SendCustomTemplateEmail($template, $candidate, null))->handle();

    Http::assertNothingSent();
    expect($candidate->activities()->count())->toBe(0);
});

test('it sends a custom template to a client via its booking contact', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id, 'name' => 'Acme Ltd']);
    $contact = ClientContact::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $client->id,
        'first_name' => 'Sam',
        'last_name' => 'Smith',
        'email' => 'sam@acme.test',
        'booking_contact' => true,
    ]);

    $template = EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Custom client template',
        'type' => EmailTemplateType::Custom->value,
        'audience' => EmailTemplateAudience::Client->value,
        'subject' => 'Hello {recipient_first_name}',
        'body' => 'Dear {recipient_name} at {client_name}',
    ]);

    (new SendCustomTemplateEmail($template, $client, null))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'users/sender@example.com/sendMail')
        && str_contains($request['message']['subject'], 'Hello Sam')
        && str_contains($request['message']['body']['content'], 'Dear Sam Smith at Acme Ltd')
        && $request['message']['toRecipients'][0]['emailAddress']['address'] === $contact->email);

    expect($client->activities()->count())->toBe(1);
});

test('it does not send when a client has no bookable contact at all', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id]);

    $template = EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Custom client template',
        'type' => EmailTemplateType::Custom->value,
        'audience' => EmailTemplateAudience::Client->value,
        'subject' => 'Hello',
        'body' => 'Hi',
    ]);

    (new SendCustomTemplateEmail($template, $client, null))->handle();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'graph.microsoft.com'));
    expect($client->activities()->count())->toBe(0);
});
