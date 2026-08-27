<?php

use App\Enums\EmailTemplateType;
use App\Filament\Resources\MarketingCampaigns\Pages\EditMarketingCampaign;
use App\Jobs\SendCustomTemplateEmail;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientContactJobTitle;
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

    $this->campaign = MarketingCampaign::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $this->template = EmailTemplate::create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Campaign template',
        'type' => EmailTemplateType::Custom->value,
        'audience' => 'client',
        'subject' => 'Hello {recipient_first_name}',
        'body' => 'Hi {recipient_name}',
    ]);
});

function makeCampaignClientWithContact(): Client
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

test('sending from the campaign page dispatches the job with the campaign attached, for each client on the campaign', function () {
    Bus::fake();

    $client = makeCampaignClientWithContact();
    $this->campaign->clients()->attach($client);

    Livewire::test(EditMarketingCampaign::class, ['record' => $this->campaign->getRouteKey()])
        ->callAction('sendCampaignEmail', data: [
            'mode' => 'template',
            'email_template_id' => $this->template->id,
        ]);

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->recipient->is($client)
        && $job->campaign->is($this->campaign)
        && $job->template->is($this->template));
});

test('sending an ad-hoc campaign email dispatches with no template and the raw content', function () {
    Bus::fake();

    $client = makeCampaignClientWithContact();
    $this->campaign->clients()->attach($client);

    Livewire::test(EditMarketingCampaign::class, ['record' => $this->campaign->getRouteKey()])
        ->callAction('sendCampaignEmail', data: [
            'mode' => 'adhoc',
            'adhoc_subject' => 'Big news',
            'adhoc_body' => 'Check this out',
        ]);

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->recipient->is($client)
        && $job->campaign->is($this->campaign)
        && $job->template === null
        && $job->adHocSubject === 'Big news');
});

test('clients on the campaign with no bookable contact are skipped and reported by name', function () {
    Bus::fake();

    $sendable = makeCampaignClientWithContact();
    $skipped = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'No Contact Ltd',
    ]);
    $this->campaign->clients()->attach([$sendable->id, $skipped->id]);

    Livewire::test(EditMarketingCampaign::class, ['record' => $this->campaign->getRouteKey()])
        ->callAction('sendCampaignEmail', data: [
            'mode' => 'template',
            'email_template_id' => $this->template->id,
        ])
        ->assertNotified('Queued 1 email(s). Skipped 1 (no contact email on file): No Contact Ltd');

    Bus::assertDispatched(SendCustomTemplateEmail::class, 1);
});

test('only clients attached to the campaign receive the email, not every client', function () {
    Bus::fake();

    $inCampaign = makeCampaignClientWithContact();
    $notInCampaign = makeCampaignClientWithContact();
    $this->campaign->clients()->attach($inCampaign);

    Livewire::test(EditMarketingCampaign::class, ['record' => $this->campaign->getRouteKey()])
        ->callAction('sendCampaignEmail', data: [
            'mode' => 'template',
            'email_template_id' => $this->template->id,
        ]);

    Bus::assertDispatched(SendCustomTemplateEmail::class, 1);
    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->recipient->is($inCampaign));
});

test('when the campaign has client job titles, it emails every matching contact per client instead of just the booking contact', function () {
    Bus::fake();

    $senco = ClientContactJobTitle::factory()->create(['company_id' => $this->user->company_id, 'industry_id' => $this->industry->id]);
    $headteacher = ClientContactJobTitle::factory()->create(['company_id' => $this->user->company_id, 'industry_id' => $this->industry->id]);
    $this->campaign->update(['client_job_titles' => [$senco->id, $headteacher->id]]);

    $client = Client::factory()->create(['company_id' => $this->user->company_id, 'industry_id' => $this->industry->id]);
    $sencoContact = ClientContact::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'client_contact_job_title_id' => $senco->id,
        'email' => 'senco@school.test',
    ]);
    $headteacherContact = ClientContact::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'client_contact_job_title_id' => $headteacher->id,
        'main_contact' => true,
        'email' => 'head@school.test',
    ]);
    ClientContact::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'email' => 'other@school.test',
    ]);
    $this->campaign->clients()->attach($client);

    Livewire::test(EditMarketingCampaign::class, ['record' => $this->campaign->getRouteKey()])
        ->callAction('sendCampaignEmail', data: [
            'mode' => 'template',
            'email_template_id' => $this->template->id,
        ]);

    Bus::assertDispatched(SendCustomTemplateEmail::class, 2);
    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->contact?->is($sencoContact));
    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->contact?->is($headteacherContact));
});

test('a client with no contact matching the campaign job titles falls back to its main contact', function () {
    Bus::fake();

    $senco = ClientContactJobTitle::factory()->create(['company_id' => $this->user->company_id, 'industry_id' => $this->industry->id]);
    $this->campaign->update(['client_job_titles' => [$senco->id]]);

    $client = makeCampaignClientWithContact();
    $mainContact = $client->mainContact;
    $this->campaign->clients()->attach($client);

    Livewire::test(EditMarketingCampaign::class, ['record' => $this->campaign->getRouteKey()])
        ->callAction('sendCampaignEmail', data: [
            'mode' => 'template',
            'email_template_id' => $this->template->id,
        ]);

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->contact?->is($mainContact));
});

test('a client with no contact matching the campaign job titles and no main contact is skipped', function () {
    Bus::fake();

    $senco = ClientContactJobTitle::factory()->create(['company_id' => $this->user->company_id, 'industry_id' => $this->industry->id]);
    $this->campaign->update(['client_job_titles' => [$senco->id]]);

    $client = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'No Match Ltd',
    ]);
    $this->campaign->clients()->attach($client);

    Livewire::test(EditMarketingCampaign::class, ['record' => $this->campaign->getRouteKey()])
        ->callAction('sendCampaignEmail', data: [
            'mode' => 'template',
            'email_template_id' => $this->template->id,
        ])
        ->assertNotified('Queued 0 email(s). Skipped 1 (no contact email on file): No Match Ltd');

    Bus::assertNotDispatched(SendCustomTemplateEmail::class);
});
