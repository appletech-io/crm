<?php

use App\Enums\EmailTemplateType;
use App\Filament\Resources\HealthcareCandidates\Pages\EditHealthcareCandidate;
use App\Filament\Resources\HealthcareCandidates\Pages\ListHealthcareCandidates;
use App\Jobs\SendCustomTemplateEmail;
use App\Models\EmailTemplate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'healthcare']);
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

test('the row action dispatches the job for the selected candidate and template', function () {
    Bus::fake();

    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'email' => 'jane@example.com',
    ]);

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'all')
        ->callTableAction('sendEmail', $candidate, data: ['email_template_id' => $this->template->id])
        ->assertNotified('Email queued for sending');

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->template->is($this->template) && $job->recipient->is($candidate));
});

test('the bulk action dispatches for sendable candidates and reports skipped ones', function () {
    Bus::fake();

    $sendable = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'email' => 'jane@example.com',
    ]);
    $skipped = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'No',
        'last_name' => 'Email',
        'email' => null,
    ]);

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'all')
        ->callTableBulkAction('sendEmail', [$sendable, $skipped], data: ['email_template_id' => $this->template->id])
        ->assertNotified('Queued 1 email(s). Skipped 1 (no contact email on file): No Email');

    Bus::assertDispatched(SendCustomTemplateEmail::class, 1);
});

test('the edit page header action sends to the candidate being edited', function () {
    Bus::fake();

    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'email' => 'jane@example.com',
    ]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->callAction('sendEmail', data: ['email_template_id' => $this->template->id])
        ->assertNotified('Email queued for sending');

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->recipient->is($candidate));
});
