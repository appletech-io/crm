<?php

use App\Enums\EmailTemplateType;
use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Filament\Resources\EducationCandidates\Pages\ListEducationCandidates;
use App\Http\Controllers\EmailImageController;
use App\Jobs\SendCustomTemplateEmail;
use App\Models\CandidateCandidateStatus;
use App\Models\CandidateStatus;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\EmailTemplate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->template = EmailTemplate::create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Candidate custom template',
        'type' => EmailTemplateType::Custom->value,
        'audience' => 'candidate',
        'subject' => 'Hello {recipient_first_name}',
        'body' => 'Hi {recipient_name}',
    ]);
});

test('the template picker only offers Custom templates for the matching audience, company, and industry', function () {
    // Decoys that must never appear in the options.
    EmailTemplate::create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Client-only template',
        'type' => EmailTemplateType::Custom->value,
        'audience' => 'client',
        'subject' => 'x',
        'body' => 'x',
    ]);
    EmailTemplate::create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Automated template',
        'type' => EmailTemplateType::ReferenceRequest->value,
        'subject' => 'x',
        'body' => 'x',
    ]);
    $otherCompanyTemplate = EmailTemplate::create([
        'company_id' => Company::factory()->create()->id,
        'industry_id' => $this->industry->id,
        'name' => 'Other company template',
        'type' => EmailTemplateType::Custom->value,
        'audience' => 'candidate',
        'subject' => 'x',
        'body' => 'x',
    ]);

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'all')
        ->mountTableAction('sendEmail', $candidate)
        ->assertFormFieldExists('email_template_id', function (Select $field) use ($otherCompanyTemplate): bool {
            $options = $field->getOptions();

            return in_array($this->template->name, $options, true)
                && ! in_array('Client-only template', $options, true)
                && ! in_array('Automated template', $options, true)
                && ! in_array($otherCompanyTemplate->name, $options, true);
        });
});

test('the row action dispatches the job for the selected candidate and template', function () {
    Bus::fake();

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'email' => 'jane@example.com',
    ]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'all')
        ->callTableAction('sendEmail', $candidate, data: ['email_template_id' => $this->template->id])
        ->assertNotified('Email queued for sending');

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->template->is($this->template) && $job->recipient->is($candidate));
});

test('the row action reports failure for a candidate with no email instead of dispatching', function () {
    Bus::fake();

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'email' => null,
    ]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'all')
        ->callTableAction('sendEmail', $candidate, data: ['email_template_id' => $this->template->id])
        ->assertNotified('Cannot send — no contact email on file');

    Bus::assertNotDispatched(SendCustomTemplateEmail::class);
});

test('the bulk action dispatches for sendable candidates and reports skipped ones', function () {
    Bus::fake();

    $sendable = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'email' => 'jane@example.com',
    ]);
    $skipped = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'No',
        'last_name' => 'Email',
        'email' => null,
    ]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'all')
        ->callTableBulkAction('sendEmail', [$sendable, $skipped], data: ['email_template_id' => $this->template->id])
        ->assertNotified('Queued 1 email(s). Skipped 1 (no contact email on file): No Email');

    Bus::assertDispatched(SendCustomTemplateEmail::class, 1);
    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->recipient->is($sendable));
});

test('the edit page header action sends to the candidate being edited', function () {
    Bus::fake();

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'email' => 'jane@example.com',
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->callAction('sendEmail', data: ['email_template_id' => $this->template->id])
        ->assertNotified('Email queued for sending');

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->recipient->is($candidate));
});

test('the bulk action is also available on the search tab', function () {
    Bus::fake();

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'consultant_id' => $this->user->id,
        'email' => 'jane@example.com',
    ]);

    $status = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Live',
    ]);
    CandidateCandidateStatus::create([
        'model_type' => EducationCandidate::class,
        'model_id' => $candidate->id,
        'candidate_status_id' => $status->id,
    ]);

    // activeSection defaults to 'search', so this exercises configureSearchTable().
    Livewire::test(ListEducationCandidates::class)
        ->callTableBulkAction('sendEmail', [$candidate], data: ['email_template_id' => $this->template->id])
        ->assertNotified('Queued 1 email(s)');

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->recipient->is($candidate));
});

test('the row action can send an ad-hoc email with no saved template', function () {
    Bus::fake();

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'email' => 'jane@example.com',
    ]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'all')
        ->callTableAction('sendEmail', $candidate, data: [
            'mode' => 'adhoc',
            'adhoc_subject' => 'Quick note',
            'adhoc_body' => 'Just checking in.',
        ])
        ->assertNotified('Email queued for sending');

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->template === null
        && $job->recipient->is($candidate)
        && $job->adHocSubject === 'Quick note'
        && $job->adHocBody === 'Just checking in.');
});

test('the bulk action can send an ad-hoc email to sendable candidates', function () {
    Bus::fake();

    $sendable = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'email' => 'jane@example.com',
    ]);
    $skipped = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'No',
        'last_name' => 'Email',
        'email' => null,
    ]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'all')
        ->callTableBulkAction('sendEmail', [$sendable, $skipped], data: [
            'mode' => 'adhoc',
            'adhoc_subject' => 'Quick note',
            'adhoc_body' => 'Just checking in.',
        ])
        ->assertNotified('Queued 1 email(s). Skipped 1 (no contact email on file): No Email');

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->template === null && $job->recipient->is($sendable));
});

test('the ad-hoc body field points a pasted image at a signed email-images url, not a raw storage url', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $path = EmailImageController::DIRECTORY.'/abc123.png';

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'all')
        ->mountTableAction('sendEmail', $candidate)
        ->assertFormFieldExists('adhoc_body', fn (RichEditor $field): bool => $field->getFileAttachmentUrl($path)
            === URL::signedRoute('email-images.show', ['path' => $path]));
});
