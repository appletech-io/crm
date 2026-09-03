<?php

use App\Enums\EmailTemplateAudience;
use App\Enums\EmailTemplateType;
use App\Http\Controllers\EmailImageController;
use App\Jobs\SendCustomTemplateEmail;
use App\Models\CampaignSend;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\EmailTemplate;
use App\Models\Industry;
use App\Models\MarketingCampaign;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->company = Company::factory()->create([
        'ms_tenant_id' => 'tenant',
        'ms_client_id' => 'client',
        'ms_client_secret' => 'secret',
        'ms_sender_email' => 'sender@example.com',
    ]);
    $this->industry = Industry::factory()->create(['slug' => 'education']);

    Storage::fake('local');
    config(['filesystems.default' => 'local']);

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

test('it sends from the compliance officer when the template requests it and no one is actively sending it', function () {
    $complianceOfficer = User::factory()->create(['company_id' => $this->company->id, 'email' => 'compliance@example.com']);
    $consultant = User::factory()->create([
        'company_id' => $this->company->id,
        'email' => 'consultant@example.com',
        'compliance_officer_id' => $complianceOfficer->id,
    ]);
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'email' => 'jane@example.com',
        'consultant_id' => $consultant->id,
    ]);

    $template = EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Custom candidate template',
        'type' => EmailTemplateType::Custom->value,
        'audience' => EmailTemplateAudience::Candidate->value,
        'sender' => 'compliance_officer',
        'subject' => 'Hello',
        'body' => 'Hi',
    ]);

    (new SendCustomTemplateEmail($template, $candidate, null))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), "users/{$complianceOfficer->email}/sendMail"));
});

test('it falls back to the consultant when the template requests the compliance officer but none is assigned, and no one is actively sending it', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id, 'email' => 'consultant@example.com']);
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'email' => 'jane@example.com',
        'consultant_id' => $consultant->id,
    ]);

    $template = EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Custom candidate template',
        'type' => EmailTemplateType::Custom->value,
        'audience' => EmailTemplateAudience::Candidate->value,
        'sender' => 'compliance_officer',
        'subject' => 'Hello',
        'body' => 'Hi',
    ]);

    (new SendCustomTemplateEmail($template, $candidate, null))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), "users/{$consultant->email}/sendMail"));
});

test('it sends from whoever is actively sending it, not the candidates assigned consultant', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id, 'email' => 'consultant@example.com']);
    $sendingUser = User::factory()->create(['company_id' => $this->company->id, 'email' => 'sending-admin@example.com']);
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'email' => 'jane@example.com',
        'consultant_id' => $consultant->id,
    ]);

    $template = EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Custom candidate template',
        'type' => EmailTemplateType::Custom->value,
        'audience' => EmailTemplateAudience::Candidate->value,
        'subject' => 'Hello',
        'body' => 'Hi',
    ]);

    (new SendCustomTemplateEmail($template, $candidate, $sendingUser->id))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), "users/{$sendingUser->email}/sendMail"));
});

test('it sends from whoever is actively sending it even when the template requests the compliance officer', function () {
    $complianceOfficer = User::factory()->create(['company_id' => $this->company->id, 'email' => 'compliance@example.com']);
    $consultant = User::factory()->create([
        'company_id' => $this->company->id,
        'email' => 'consultant@example.com',
        'compliance_officer_id' => $complianceOfficer->id,
    ]);
    $sendingUser = User::factory()->create(['company_id' => $this->company->id, 'email' => 'sending-admin@example.com']);
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'email' => 'jane@example.com',
        'consultant_id' => $consultant->id,
    ]);

    $template = EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Custom candidate template',
        'type' => EmailTemplateType::Custom->value,
        'audience' => EmailTemplateAudience::Candidate->value,
        'sender' => 'compliance_officer',
        'subject' => 'Hello',
        'body' => 'Hi',
    ]);

    (new SendCustomTemplateEmail($template, $candidate, $sendingUser->id))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), "users/{$sendingUser->email}/sendMail"));
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

test('it sends an ad-hoc email with no saved template, resolving placeholders the same way', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id, 'email' => 'consultant@example.com']);
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'consultant_id' => $consultant->id,
    ]);

    (new SendCustomTemplateEmail(
        template: null,
        recipient: $candidate,
        sentByUserId: $consultant->id,
        adHocSubject: 'Quick note for {recipient_first_name}',
        adHocBody: 'Hi {recipient_name}, just checking in.',
    ))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), "users/{$consultant->email}/sendMail")
        && str_contains($request['message']['subject'], 'Quick note for Jane')
        && str_contains($request['message']['body']['content'], 'Hi Jane Doe, just checking in.'));

    $activity = $candidate->activities()->latest()->first();
    expect($activity->user_id)->toBe($consultant->id);
    expect($activity->note)->toBe('Email sent: Quick note for Jane');
});

test('it records a campaign send when a campaign is passed, with the resolved content', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $contact = ClientContact::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $client->id,
        'first_name' => 'Sam',
        'last_name' => 'Smith',
        'email' => 'sam@acme.test',
        'main_contact' => true,
    ]);
    $campaign = MarketingCampaign::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    $template = EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Campaign template',
        'type' => EmailTemplateType::Custom->value,
        'audience' => EmailTemplateAudience::Client->value,
        'subject' => 'Hello {recipient_first_name}',
        'body' => 'Dear {recipient_name}',
    ]);

    (new SendCustomTemplateEmail($template, $client, null, campaign: $campaign))->handle();

    expect($campaign->sends()->count())->toBe(1);

    $send = $campaign->sends()->first();
    expect($send->client_id)->toBe($client->id);
    expect($send->client_contact_id)->toBe($contact->id);
    expect($send->email_template_id)->toBe($template->id);
    expect($send->subject)->toBe('Hello Sam');
    expect($send->body)->toBe('Dear Sam Smith');
});

test('it records a campaign send for an ad-hoc email with a null template_id', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id]);
    ClientContact::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $client->id,
        'main_contact' => true,
        'email' => 'sam@acme.test',
    ]);
    $campaign = MarketingCampaign::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    (new SendCustomTemplateEmail(
        template: null,
        recipient: $client,
        campaign: $campaign,
        adHocSubject: 'Hi there',
        adHocBody: 'Just a note',
    ))->handle();

    $send = $campaign->sends()->first();
    expect($send->email_template_id)->toBeNull();
    expect($send->subject)->toBe('Hi there');
});

test('it does not record a campaign send for a candidate recipient, even if a campaign is passed', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'email' => 'jane@example.com']);
    $campaign = MarketingCampaign::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    (new SendCustomTemplateEmail(
        template: null,
        recipient: $candidate,
        campaign: $campaign,
        adHocSubject: 'Hi',
        adHocBody: 'Body',
    ))->handle();

    expect(CampaignSend::count())->toBe(0);
});

test('a test send goes to the override address instead of the recipients real contact, with a prefixed subject and no activity logged', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id, 'name' => 'Acme Ltd']);
    ClientContact::factory()->create([
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

    (new SendCustomTemplateEmail($template, $client, null, testRecipientEmail: 'consultant@example.com'))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'users/sender@example.com/sendMail')
        && $request['message']['subject'] === '[TEST] Hello Sam'
        && str_contains($request['message']['body']['content'], 'Dear Sam Smith at Acme Ltd')
        && $request['message']['toRecipients'][0]['emailAddress']['address'] === 'consultant@example.com');

    expect($client->activities()->count())->toBe(0);
});

test('a test send does not require the recipient to have a real contact email at all', function () {
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

    (new SendCustomTemplateEmail($template, $client, null, testRecipientEmail: 'consultant@example.com'))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMail')
        && $request['message']['toRecipients'][0]['emailAddress']['address'] === 'consultant@example.com');
});

test('a test send does not record a campaign send even when a campaign is passed', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id]);
    ClientContact::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $client->id,
        'main_contact' => true,
        'email' => 'sam@acme.test',
    ]);
    $campaign = MarketingCampaign::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    (new SendCustomTemplateEmail(
        template: null,
        recipient: $client,
        campaign: $campaign,
        adHocSubject: 'Hi',
        adHocBody: 'Body',
        testRecipientEmail: 'consultant@example.com',
    ))->handle();

    expect(CampaignSend::count())->toBe(0);
});

test('it does not record a campaign send when no campaign is passed', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id]);
    ClientContact::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $client->id,
        'main_contact' => true,
        'email' => 'sam@acme.test',
    ]);

    (new SendCustomTemplateEmail(template: null, recipient: $client, adHocSubject: 'Hi', adHocBody: 'Body'))->handle();

    expect(CampaignSend::count())->toBe(0);
});

test('a pasted image in the body is sent as a cid inline attachment rather than a hotlinked signed url', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'email' => 'jane@example.com']);

    $imagePath = EmailImageController::ADHOC_DIRECTORY.'/screenshot.png';
    Storage::disk('local')->put($imagePath, 'the actual image bytes');
    $imageUrl = URL::signedRoute('email-images.show', ['path' => $imagePath]);

    (new SendCustomTemplateEmail(
        template: null,
        recipient: $candidate,
        adHocSubject: 'Hi',
        adHocBody: "<p>Look at this:</p><img src=\"{$imageUrl}\">",
    ))->handle();

    Http::assertSent(function ($request) use ($imageUrl): bool {
        if (! str_contains($request->url(), 'sendMail')) {
            return false;
        }

        $content = $request['message']['body']['content'];
        $inline = collect($request['message']['attachments'])->firstWhere('name', 'screenshot.png');

        return ! str_contains($content, $imageUrl)
            && str_contains($content, "cid:{$inline['contentId']}")
            && $inline['contentBytes'] === base64_encode('the actual image bytes');
    });
});

test('an ad-hoc file attachment is sent as a real (non-inline) attachment', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'email' => 'jane@example.com']);

    $attachmentPath = EmailImageController::ADHOC_DIRECTORY.'/'.Str::random(10).'/invoice.pdf';
    Storage::disk('local')->put($attachmentPath, 'the actual pdf bytes');

    (new SendCustomTemplateEmail(
        template: null,
        recipient: $candidate,
        adHocSubject: 'Hi',
        adHocBody: 'Please see attached.',
        adHocAttachments: [$attachmentPath],
    ))->handle();

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), 'sendMail')) {
            return false;
        }

        $attachment = collect($request['message']['attachments'])->firstWhere('name', 'invoice.pdf');

        return $attachment
            && $attachment['isInline'] === false
            && $attachment['contentBytes'] === base64_encode('the actual pdf bytes');
    });
});

test('ad-hoc images and attachments are not deleted immediately after sending, since a bulk send shares them across several queued jobs', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'email' => 'jane@example.com']);

    $imagePath = EmailImageController::ADHOC_DIRECTORY.'/screenshot.png';
    Storage::disk('local')->put($imagePath, 'bytes');
    $imageUrl = URL::signedRoute('email-images.show', ['path' => $imagePath]);

    $attachmentPath = EmailImageController::ADHOC_DIRECTORY.'/'.Str::random(10).'/invoice.pdf';
    Storage::disk('local')->put($attachmentPath, 'bytes');

    (new SendCustomTemplateEmail(
        template: null,
        recipient: $candidate,
        adHocSubject: 'Hi',
        adHocBody: "<img src=\"{$imageUrl}\">",
        adHocAttachments: [$attachmentPath],
    ))->handle();

    Storage::disk('local')->assertExists($imagePath);
    Storage::disk('local')->assertExists($attachmentPath);
});

test('a saved templates embedded image is also sent as a cid attachment, and the template file is left untouched', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'email' => 'jane@example.com']);

    $imagePath = EmailImageController::DIRECTORY.'/logo.png';
    Storage::disk('local')->put($imagePath, 'template image bytes');
    $imageUrl = URL::signedRoute('email-images.show', ['path' => $imagePath]);

    $template = EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Template with an image',
        'type' => EmailTemplateType::Custom->value,
        'audience' => EmailTemplateAudience::Candidate->value,
        'subject' => 'Hello',
        'body' => "<img src=\"{$imageUrl}\">",
    ]);

    (new SendCustomTemplateEmail($template, $candidate, null))->handle();

    Http::assertSent(function ($request) use ($imageUrl): bool {
        if (! str_contains($request->url(), 'sendMail')) {
            return false;
        }

        $content = $request['message']['body']['content'];
        $inline = collect($request['message']['attachments'])->firstWhere('name', 'logo.png');

        return ! str_contains($content, $imageUrl) && $inline && $inline['isInline'] === true;
    });

    Storage::disk('local')->assertExists($imagePath);
});

test('the campaign send record stores the raw body with its original signed image url, not the cid-rewritten version sent to the mailer', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id]);
    ClientContact::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $client->id,
        'main_contact' => true,
        'email' => 'sam@acme.test',
    ]);
    $campaign = MarketingCampaign::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    $imagePath = EmailImageController::ADHOC_DIRECTORY.'/screenshot.png';
    Storage::disk('local')->put($imagePath, 'bytes');
    $imageUrl = URL::signedRoute('email-images.show', ['path' => $imagePath]);

    (new SendCustomTemplateEmail(
        template: null,
        recipient: $client,
        campaign: $campaign,
        adHocSubject: 'Hi',
        adHocBody: "<img src=\"{$imageUrl}\">",
    ))->handle();

    expect($campaign->sends()->first()->body)->toContain($imageUrl);
});
