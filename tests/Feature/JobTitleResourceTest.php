<?php

use App\Filament\Pages\ClientSettings;
use App\Filament\Resources\JobTitles\Pages\CreateJobTitle;
use App\Filament\Resources\JobTitles\Pages\EditJobTitle;
use App\Filament\Resources\JobTitles\Pages\ListJobTitles;
use App\Models\Company;
use App\Models\Industry;
use App\Models\JobTitle;
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
    Livewire::test(ListJobTitles::class)
        ->assertSuccessful();
});

test('non-admin cannot access job titles resource', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id]);
    $consultant->industries()->attach($this->industry);
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);

    Livewire::test(ListJobTitles::class)->assertForbidden();
});

test('site_admin can access job titles resource', function () {
    $siteAdmin = User::factory()->create(['company_id' => $this->company->id]);
    $siteAdmin->industries()->attach($this->industry);
    $siteAdmin->assignRole('site_admin');
    $this->actingAs($siteAdmin);

    Cache::put("user.{$siteAdmin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$siteAdmin->id}.active_industry_id", $this->industry->id);

    Livewire::test(ListJobTitles::class)->assertSuccessful();
});

test('client settings page is only accessible to admin and site_admin', function () {
    expect(ClientSettings::canAccess())->toBeTrue();

    $consultant = User::factory()->create(['company_id' => $this->company->id]);
    $consultant->industries()->attach($this->industry);
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);

    expect(ClientSettings::canAccess())->toBeFalse();
});

test('can create a job title', function () {
    Livewire::test(CreateJobTitle::class)
        ->fillForm(['name' => 'Software Engineer'])
        ->call('create')
        ->assertHasNoFormErrors();

    $jobTitle = JobTitle::where('name', 'Software Engineer')->first();

    expect($jobTitle)->not->toBeNull()
        ->and($jobTitle->company_id)->toBe($this->company->id)
        ->and($jobTitle->industry_id)->toBe($this->industry->id);
});

test('edit page renders', function () {
    $jobTitle = JobTitle::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(EditJobTitle::class, ['record' => $jobTitle->getRouteKey()])
        ->assertSuccessful();
});

test('job title name can be updated', function () {
    $jobTitle = JobTitle::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Old Title',
    ]);

    Livewire::test(EditJobTitle::class, ['record' => $jobTitle->getRouteKey()])
        ->fillForm(['name' => 'New Title'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($jobTitle->refresh()->name)->toBe('New Title');
});

test('job titles are scoped to the current company and industry', function () {
    $otherCompany = Company::factory()->create();
    JobTitle::factory()->create([
        'company_id' => $otherCompany->id,
        'industry_id' => $this->industry->id,
        'name' => 'Other Company Title',
    ]);

    JobTitle::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'My Title',
    ]);

    Livewire::test(ListJobTitles::class)
        ->assertCanSeeTableRecords(JobTitle::where('name', 'My Title')->get())
        ->assertCanNotSeeTableRecords(JobTitle::where('name', 'Other Company Title')->get());
});
