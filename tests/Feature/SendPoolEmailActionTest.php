<?php

use App\Enums\EmailTemplateType;
use App\Filament\Resources\ClientPools\Pages\EditClientPool;
use App\Jobs\SendCustomTemplateEmail;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientPool;
use App\Models\EmailTemplate;
use App\Models\Industry;
use App\Models\MarketingCampaign;
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

    $this->pool = ClientPool::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'user_id' => $this->user->id,
    ]);

    $this->template = EmailTemplate::create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Pool template',
        'type' => EmailTemplateType::Custom->value,
        'audience' => 'client',
        'subject' => 'Hello {recipient_first_name}',
        'body' => 'Hi {recipient_name}',
    ]);
});

function makePoolClientWithContact(): Client
{
    $client = Client::factory()->create(['company_id' => test()->user->company_id, 'industry_id' => test()->industry->id]);
    ClientContact::factory()->create([
        'company_id' => test()->user->company_id,
        'client_id' => $client->id,
        'main_contact' => true,
        'email' => 'contact@acme.test',
    ]);

    return $client;
}

test('sending from the pool dispatches the job for every client in the pool', function () {
    Bus::fake();

    $clientA = makePoolClientWithContact();
    $clientB = makePoolClientWithContact();
    $this->pool->clients()->attach([$clientA->id, $clientB->id]);

    Livewire::test(EditClientPool::class, ['record' => $this->pool->getRouteKey()])
        ->callAction('sendPoolEmail', data: [
            'mode' => 'template',
            'email_template_id' => $this->template->id,
        ])
        ->assertNotified('Queued 2 email(s)');

    Bus::assertDispatched(SendCustomTemplateEmail::class, 2);
    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->recipient->is($clientA) && $job->campaign === null);
});

test('sending from the pool with a campaign selected tags the dispatched job with it', function () {
    Bus::fake();

    $client = makePoolClientWithContact();
    $this->pool->clients()->attach($client);

    $campaign = MarketingCampaign::factory()->create(['company_id' => $this->user->company_id, 'industry_id' => $this->industry->id]);

    Livewire::test(EditClientPool::class, ['record' => $this->pool->getRouteKey()])
        ->callAction('sendPoolEmail', data: [
            'mode' => 'template',
            'email_template_id' => $this->template->id,
            'marketing_campaign_id' => $campaign->id,
        ]);

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->recipient->is($client) && $job->campaign->is($campaign));
});

test('clients in the pool with no bookable contact are skipped and reported by name', function () {
    Bus::fake();

    $sendable = makePoolClientWithContact();
    $skipped = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'No Contact Ltd',
    ]);
    $this->pool->clients()->attach([$sendable->id, $skipped->id]);

    Livewire::test(EditClientPool::class, ['record' => $this->pool->getRouteKey()])
        ->callAction('sendPoolEmail', data: [
            'mode' => 'adhoc',
            'adhoc_subject' => 'Hi',
            'adhoc_body' => 'Body',
        ])
        ->assertNotified('Queued 1 email(s). Skipped 1 (no contact email on file): No Contact Ltd');

    Bus::assertDispatched(SendCustomTemplateEmail::class, 1);
});
