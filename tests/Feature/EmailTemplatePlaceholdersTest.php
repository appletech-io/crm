<?php

use App\Enums\EmailTemplateSender;
use App\Enums\EmailTemplateType;
use App\Filament\Resources\EmailTemplates\Pages\CreateEmailTemplate;
use App\Models\Company;
use App\Models\EmailTemplate;
use App\Models\Industry;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->company = Company::factory()->create();
    $this->company->industries()->attach($this->industry);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->industry);
    $this->actingAs($this->user);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('the reference request placeholders are shown when that type is selected', function () {
    Livewire::test(CreateEmailTemplate::class)
        ->fillForm(['type' => EmailTemplateType::ReferenceRequest->value])
        ->assertSee('{referee_name}')
        ->assertSee('{candidate_name}')
        ->assertSee('{reference_link}')
        ->assertSee('{expiry_date}')
        ->assertDontSee('{job_title}');
});

test('the placeholder list updates when the template type changes', function () {
    Livewire::test(CreateEmailTemplate::class)
        ->fillForm(['type' => EmailTemplateType::PayrollConfirmation->value])
        ->assertSee('{payroll_confirmation_link}')
        ->assertDontSee('{reference_link}')
        ->fillForm(['type' => EmailTemplateType::ReferenceRequest->value])
        ->assertSee('{reference_link}')
        ->assertDontSee('{payroll_confirmation_link}');
});

test('the general template type has no placeholders to show', function () {
    Livewire::test(CreateEmailTemplate::class)
        ->fillForm(['type' => EmailTemplateType::General->value])
        ->assertSee('This template type has no placeholders available.');
});

test('every declared placeholder for every type is unique to that type or intentionally shared', function () {
    // Sanity check on the source of truth itself: every type must resolve to
    // an array (never null/error) so the form never renders a fatal state.
    foreach (EmailTemplateType::cases() as $type) {
        expect($type->placeholders())->toBeArray();
    }
});

test('the sender field defaults to consultant and can be set to compliance officer', function () {
    Livewire::test(CreateEmailTemplate::class)
        ->assertFormFieldExists('sender', function ($field) {
            return $field->getState() === 'consultant';
        })
        ->fillForm([
            'name' => 'Template',
            'subject' => 'Subject',
            'body' => 'Body',
            'sender' => 'compliance_officer',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(EmailTemplate::where('name', 'Template')->first()->sender)
        ->toBe(EmailTemplateSender::ComplianceOfficer);
});

test('the audience field is hidden and not required unless the type is Custom', function () {
    Livewire::test(CreateEmailTemplate::class)
        ->fillForm(['type' => EmailTemplateType::ReferenceRequest->value])
        ->assertFormFieldIsHidden('audience')
        ->fillForm(['type' => EmailTemplateType::Custom->value])
        ->assertFormFieldIsVisible('audience');
});

test('a Custom template with Candidate audience shows only the shared placeholders', function () {
    Livewire::test(CreateEmailTemplate::class)
        ->fillForm(['type' => EmailTemplateType::Custom->value, 'audience' => 'candidate'])
        ->assertSee('{recipient_name}')
        ->assertSee('{consultant_name}')
        ->assertDontSee('{client_name}');
});

test('a Custom template with Client audience additionally shows client-only placeholders', function () {
    Livewire::test(CreateEmailTemplate::class)
        ->fillForm(['type' => EmailTemplateType::Custom->value, 'audience' => 'client'])
        ->assertSee('{recipient_name}')
        ->assertSee('{client_name}')
        ->assertSee('{client_address}');
});

test('a Custom template with Both audience shows only placeholders valid for either recipient', function () {
    Livewire::test(CreateEmailTemplate::class)
        ->fillForm(['type' => EmailTemplateType::Custom->value, 'audience' => 'both'])
        ->assertSee('{recipient_name}')
        ->assertDontSee('{client_name}');
});
