<?php

use App\Enums\EmailTemplateSender;
use App\Models\Company;
use App\Models\EmailTemplate;
use App\Models\Industry;
use App\Models\User;
use App\Services\Mail\EmailSenderResolver;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create();
});

test('it resolves to the consultant when the template sender is consultant', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id]);
    $template = EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Template',
        'subject' => 'Subject',
        'body' => 'Body',
        'sender' => EmailTemplateSender::Consultant->value,
    ]);

    expect(EmailSenderResolver::resolve($template, $consultant)?->is($consultant))->toBeTrue();
});

test('it resolves to the consultant when there is no template at all', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id]);

    expect(EmailSenderResolver::resolve(null, $consultant)?->is($consultant))->toBeTrue();
});

test('it resolves to the compliance officer when the template requests it and one is assigned', function () {
    $complianceOfficer = User::factory()->create(['company_id' => $this->company->id]);
    $consultant = User::factory()->create([
        'company_id' => $this->company->id,
        'compliance_officer_id' => $complianceOfficer->id,
    ]);
    $template = EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Template',
        'subject' => 'Subject',
        'body' => 'Body',
        'sender' => EmailTemplateSender::ComplianceOfficer->value,
    ]);

    expect(EmailSenderResolver::resolve($template, $consultant)?->is($complianceOfficer))->toBeTrue();
});

test('it falls back to the consultant when the template requests the compliance officer but none is assigned', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id]);
    $template = EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Template',
        'subject' => 'Subject',
        'body' => 'Body',
        'sender' => EmailTemplateSender::ComplianceOfficer->value,
    ]);

    expect(EmailSenderResolver::resolve($template, $consultant)?->is($consultant))->toBeTrue();
});

test('it resolves to null when the template requests the compliance officer but there is no consultant either', function () {
    $template = EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Template',
        'subject' => 'Subject',
        'body' => 'Body',
        'sender' => EmailTemplateSender::ComplianceOfficer->value,
    ]);

    expect(EmailSenderResolver::resolve($template, null))->toBeNull();
});

test('the actively sending user takes priority over the consultant', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id]);
    $sendingUser = User::factory()->create(['company_id' => $this->company->id]);

    expect(EmailSenderResolver::resolve(null, $consultant, $sendingUser)?->is($sendingUser))->toBeTrue();
});

test('the actively sending user takes priority even when the template requests the compliance officer', function () {
    $complianceOfficer = User::factory()->create(['company_id' => $this->company->id]);
    $consultant = User::factory()->create([
        'company_id' => $this->company->id,
        'compliance_officer_id' => $complianceOfficer->id,
    ]);
    $sendingUser = User::factory()->create(['company_id' => $this->company->id]);
    $template = EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Template',
        'subject' => 'Subject',
        'body' => 'Body',
        'sender' => EmailTemplateSender::ComplianceOfficer->value,
    ]);

    expect(EmailSenderResolver::resolve($template, $consultant, $sendingUser)?->is($sendingUser))->toBeTrue();
});

test('with no sending user, resolution falls through to the template/consultant as before', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id]);

    expect(EmailSenderResolver::resolve(null, $consultant, null)?->is($consultant))->toBeTrue();
});
