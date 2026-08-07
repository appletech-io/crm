<?php

use App\Enums\EmailTemplateType;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Jobs\SendCustomTemplateEmail;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\EmailTemplate;
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

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->template = EmailTemplate::create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Client custom template',
        'type' => EmailTemplateType::Custom->value,
        'audience' => 'client',
        'subject' => 'Hello {recipient_first_name}',
        'body' => 'Hi {recipient_name} at {client_name}',
    ]);
});

test('the row action dispatches the job using the client booking contact', function () {
    Bus::fake();

    $client = Client::factory()->create(['company_id' => $this->user->company_id, 'industry_id' => $this->industry->id, 'consultant_id' => $this->user->id]);
    ClientContact::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'main_contact' => true,
        'email' => 'contact@acme.test',
    ]);

    Livewire::test(ListClients::class)
        ->callTableAction('sendEmail', $client, data: ['email_template_id' => $this->template->id])
        ->assertNotified('Email queued for sending');

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->template->is($this->template) && $job->recipient->is($client));
});

test('the row action reports failure for a client with no bookable contact instead of dispatching', function () {
    Bus::fake();

    $client = Client::factory()->create(['company_id' => $this->user->company_id, 'industry_id' => $this->industry->id, 'consultant_id' => $this->user->id]);

    Livewire::test(ListClients::class)
        ->callTableAction('sendEmail', $client, data: ['email_template_id' => $this->template->id])
        ->assertNotified('Cannot send — no contact email on file');

    Bus::assertNotDispatched(SendCustomTemplateEmail::class);
});

test('the bulk action dispatches for sendable clients and reports skipped ones by name', function () {
    Bus::fake();

    $sendable = Client::factory()->create(['company_id' => $this->user->company_id, 'industry_id' => $this->industry->id, 'consultant_id' => $this->user->id, 'name' => 'Acme Ltd']);
    ClientContact::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $sendable->id,
        'main_contact' => true,
        'email' => 'contact@acme.test',
    ]);
    $skipped = Client::factory()->create(['company_id' => $this->user->company_id, 'industry_id' => $this->industry->id, 'consultant_id' => $this->user->id, 'name' => 'No Contact Ltd']);

    Livewire::test(ListClients::class)
        ->callTableBulkAction('sendEmail', [$sendable, $skipped], data: ['email_template_id' => $this->template->id])
        ->assertNotified('Queued 1 email(s). Skipped 1 (no contact email on file): No Contact Ltd');

    Bus::assertDispatched(SendCustomTemplateEmail::class, 1);
});

test('the edit page header action sends to the client being edited', function () {
    Bus::fake();

    $client = Client::factory()->create(['company_id' => $this->user->company_id, 'industry_id' => $this->industry->id, 'consultant_id' => $this->user->id]);
    ClientContact::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'main_contact' => true,
        'email' => 'contact@acme.test',
    ]);

    Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
        ->callAction('sendEmail', data: ['email_template_id' => $this->template->id])
        ->assertNotified('Email queued for sending');

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->recipient->is($client));
});
