<?php

use App\Filament\Resources\MarketingCampaigns\Pages\EditMarketingCampaign;
use App\Filament\Resources\MarketingCampaigns\RelationManagers\ClientsRelationManager;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientContactJobTitle;
use App\Models\ClientPool;
use App\Models\Company;
use App\Models\Industry;
use App\Models\MarketingCampaign;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create();
    $this->company->industries()->attach($this->industry);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->industry);
    $this->user->assignRole('consultant');
    $this->actingAs($this->user);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->campaign = MarketingCampaign::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
});

test('a client can be attached to a campaign', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    Livewire::test(ClientsRelationManager::class, [
        'ownerRecord' => $this->campaign,
        'pageClass' => EditMarketingCampaign::class,
    ])
        ->callAction(TestAction::make('attach')->table(), data: ['recordId' => [$client->id]]);

    expect($this->campaign->clients()->pluck('clients.id')->all())->toBe([$client->id]);
});

test('a client can be detached from a campaign', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $this->campaign->clients()->attach($client);

    Livewire::test(ClientsRelationManager::class, [
        'ownerRecord' => $this->campaign,
        'pageClass' => EditMarketingCampaign::class,
    ])
        ->callAction(TestAction::make('detach')->table(record: $client));

    expect($this->campaign->clients()->count())->toBe(0);
});

test('add from pool attaches every client currently in that pool', function () {
    $pool = ClientPool::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'user_id' => $this->user->id,
    ]);
    $clientA = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $clientB = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $pool->clients()->attach([$clientA->id, $clientB->id]);

    Livewire::test(ClientsRelationManager::class, [
        'ownerRecord' => $this->campaign,
        'pageClass' => EditMarketingCampaign::class,
    ])
        ->callAction(TestAction::make('addFromPool')->table(), data: ['client_pool_id' => $pool->id])
        ->assertNotified("Added 2 client(s) from {$pool->name}");

    expect($this->campaign->clients()->pluck('clients.id')->sort()->values()->all())
        ->toBe(collect([$clientA->id, $clientB->id])->sort()->values()->all());
});

test('add from pool is idempotent when run twice', function () {
    $pool = ClientPool::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'user_id' => $this->user->id,
    ]);
    $client = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $pool->clients()->attach($client);
    $this->campaign->clients()->attach($client);

    Livewire::test(ClientsRelationManager::class, [
        'ownerRecord' => $this->campaign,
        'pageClass' => EditMarketingCampaign::class,
    ])
        ->callAction(TestAction::make('addFromPool')->table(), data: ['client_pool_id' => $pool->id]);

    expect($this->campaign->clients()->count())->toBe(1);
});

test('the contact match column is hidden when the campaign has no client job titles set', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $this->campaign->clients()->attach($client);

    Livewire::test(ClientsRelationManager::class, [
        'ownerRecord' => $this->campaign,
        'pageClass' => EditMarketingCampaign::class,
    ])->assertTableColumnHidden('contact_match');
});

test('the contact match column breaks down which clients have, lack, or fall back on a job title contact', function () {
    $senco = ClientContactJobTitle::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $this->campaign->update(['client_job_titles' => [$senco->id]]);

    $matched = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id, 'name' => 'Matched School']);
    ClientContact::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $matched->id,
        'client_contact_job_title_id' => $senco->id,
        'email' => 'senco@matched.test',
    ]);

    $fallback = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id, 'name' => 'Fallback School']);
    ClientContact::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $fallback->id,
        'main_contact' => true,
        'email' => 'head@fallback.test',
    ]);

    $none = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id, 'name' => 'No Contact School']);

    $this->campaign->clients()->attach([$matched->id, $fallback->id, $none->id]);

    Livewire::test(ClientsRelationManager::class, [
        'ownerRecord' => $this->campaign,
        'pageClass' => EditMarketingCampaign::class,
    ])
        ->assertTableColumnStateSet('contact_match', 'matched', $matched)
        ->assertTableColumnStateSet('contact_match', 'fallback', $fallback)
        ->assertTableColumnStateSet('contact_match', 'none', $none);
});

test('add from pool only offers pools the current user can see', function () {
    $someoneElse = User::factory()->create(['company_id' => $this->company->id]);
    $someoneElse->industries()->attach($this->industry);

    $myPool = ClientPool::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'user_id' => $this->user->id,
        'name' => 'My Pool',
    ]);
    $sharedPool = ClientPool::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'user_id' => null,
        'company_pool' => true,
        'name' => 'Shared Pool',
    ]);
    $othersPool = ClientPool::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'user_id' => $someoneElse->id,
        'name' => 'Not Mine',
    ]);

    Livewire::test(ClientsRelationManager::class, [
        'ownerRecord' => $this->campaign,
        'pageClass' => EditMarketingCampaign::class,
    ])
        ->mountAction(TestAction::make('addFromPool')->table())
        ->assertMountedActionModalSee('My Pool')
        ->assertMountedActionModalSee('Shared Pool')
        ->assertMountedActionModalDontSee('Not Mine');
});
