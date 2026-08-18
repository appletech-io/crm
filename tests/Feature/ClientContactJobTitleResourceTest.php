<?php

use App\Filament\Resources\ClientContactJobTitles\Pages\CreateClientContactJobTitle;
use App\Filament\Resources\ClientContactJobTitles\Pages\EditClientContactJobTitle;
use App\Filament\Resources\ClientContactJobTitles\Pages\ListClientContactJobTitles;
use App\Models\ClientContactJobTitle;
use App\Models\Company;
use App\Models\Industry;
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
    Livewire::test(ListClientContactJobTitles::class)
        ->assertSuccessful();
});

test('non-admin cannot access client contact job titles resource', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id]);
    $consultant->industries()->attach($this->industry);
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);

    $this->get('/crm/client-contact-job-titles')->assertRedirect('/crm');
});

test('site_admin can access client contact job titles resource', function () {
    $siteAdmin = User::factory()->create(['company_id' => $this->company->id]);
    $siteAdmin->industries()->attach($this->industry);
    $siteAdmin->assignRole('site_admin');
    $this->actingAs($siteAdmin);

    Cache::put("user.{$siteAdmin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$siteAdmin->id}.active_industry_id", $this->industry->id);

    Livewire::test(ListClientContactJobTitles::class)->assertSuccessful();
});

test('can create a client contact job title', function () {
    Livewire::test(CreateClientContactJobTitle::class)
        ->fillForm(['name' => 'HR Manager'])
        ->call('create')
        ->assertHasNoFormErrors();

    $jobTitle = ClientContactJobTitle::where('name', 'HR Manager')->first();

    expect($jobTitle)->not->toBeNull()
        ->and($jobTitle->company_id)->toBe($this->company->id)
        ->and($jobTitle->industry_id)->toBe($this->industry->id);
});

test('edit page renders', function () {
    $jobTitle = ClientContactJobTitle::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(EditClientContactJobTitle::class, ['record' => $jobTitle->getRouteKey()])
        ->assertSuccessful();
});

test('client contact job title name can be updated', function () {
    $jobTitle = ClientContactJobTitle::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Old Title',
    ]);

    Livewire::test(EditClientContactJobTitle::class, ['record' => $jobTitle->getRouteKey()])
        ->fillForm(['name' => 'New Title'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($jobTitle->refresh()->name)->toBe('New Title');
});

test('client contact job titles are scoped to the current company and industry', function () {
    $otherCompany = Company::factory()->create();
    ClientContactJobTitle::factory()->create([
        'company_id' => $otherCompany->id,
        'industry_id' => $this->industry->id,
        'name' => 'Other Company Title',
    ]);

    ClientContactJobTitle::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'My Title',
    ]);

    Livewire::test(ListClientContactJobTitles::class)
        ->assertCanSeeTableRecords(ClientContactJobTitle::where('name', 'My Title')->get())
        ->assertCanNotSeeTableRecords(ClientContactJobTitle::where('name', 'Other Company Title')->get());
});
