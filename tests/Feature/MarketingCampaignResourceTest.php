<?php

use App\Filament\Resources\MarketingCampaigns\Pages\CreateMarketingCampaign;
use App\Filament\Resources\MarketingCampaigns\Pages\EditMarketingCampaign;
use App\Filament\Resources\MarketingCampaigns\Pages\ListMarketingCampaigns;
use App\Models\Company;
use App\Models\Industry;
use App\Models\MarketingCampaign;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create();
    $this->company->industries()->attach($this->industry);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->industry);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('list page renders', function () {
    Livewire::test(ListMarketingCampaigns::class)
        ->assertSuccessful();
});

test('can create a marketing campaign', function () {
    Livewire::test(CreateMarketingCampaign::class)
        ->fillForm(['name' => 'Spring Open Day', 'description' => 'Promoting the spring open day'])
        ->call('create')
        ->assertHasNoFormErrors();

    $campaign = MarketingCampaign::where('name', 'Spring Open Day')->first();

    expect($campaign)->not->toBeNull()
        ->and($campaign->company_id)->toBe($this->company->id)
        ->and($campaign->industry_id)->toBe($this->industry->id)
        ->and($campaign->description)->toBe('Promoting the spring open day');
});

test('edit page renders', function () {
    $campaign = MarketingCampaign::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(EditMarketingCampaign::class, ['record' => $campaign->getRouteKey()])
        ->assertSuccessful();
});

test('campaign name can be updated', function () {
    $campaign = MarketingCampaign::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Old Name',
    ]);

    Livewire::test(EditMarketingCampaign::class, ['record' => $campaign->getRouteKey()])
        ->fillForm(['name' => 'New Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($campaign->refresh()->name)->toBe('New Name');
});

test('campaigns are scoped to the current company and industry', function () {
    $otherCompany = Company::factory()->create();
    MarketingCampaign::factory()->create([
        'company_id' => $otherCompany->id,
        'industry_id' => $this->industry->id,
        'name' => 'Other Company Campaign',
    ]);

    MarketingCampaign::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'My Campaign',
    ]);

    Livewire::test(ListMarketingCampaigns::class)
        ->assertCanSeeTableRecords(MarketingCampaign::where('name', 'My Campaign')->get())
        ->assertCanNotSeeTableRecords(MarketingCampaign::where('name', 'Other Company Campaign')->get());
});
