<?php

use App\Filament\Resources\Vacancies\Pages\EditVacancy;
use App\Models\Client;
use App\Models\Company;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\JobTitle;
use App\Models\User;
use App\Models\Vacancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($this->industry);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->industry);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->client = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $this->jobStatus = JobStatus::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    $this->vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
    ]);
});

test('the edit page defaults to the board view, not the details tabs', function () {
    Livewire::test(EditVacancy::class, ['record' => $this->vacancy->getRouteKey()])
        ->assertSet('viewingDetails', false);
});

test('the toggle action switches to the details view and back', function () {
    Livewire::test(EditVacancy::class, ['record' => $this->vacancy->getRouteKey()])
        ->assertSet('viewingDetails', false)
        ->callAction('toggleDetailsView')
        ->assertSet('viewingDetails', true)
        ->callAction('toggleDetailsView')
        ->assertSet('viewingDetails', false);
});

test('a detail field still saves correctly while the board view is showing (the default)', function () {
    Livewire::test(EditVacancy::class, ['record' => $this->vacancy->getRouteKey()])
        ->assertSet('viewingDetails', false)
        ->fillForm(['placement_fee_percentage' => 22])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->vacancy->fresh()->placement_fee_percentage)->toBe(22.0);
});

test('a detail field still saves correctly after switching to the details view', function () {
    Livewire::test(EditVacancy::class, ['record' => $this->vacancy->getRouteKey()])
        ->callAction('toggleDetailsView')
        ->assertSet('viewingDetails', true)
        ->fillForm(['placement_fee_percentage' => 22])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->vacancy->fresh()->placement_fee_percentage)->toBe(22.0);
});

test('the details content actually renders once toggled, not just the boolean flipping', function () {
    Livewire::test(EditVacancy::class, ['record' => $this->vacancy->getRouteKey()])
        ->assertDontSee('Vacancy Details')
        ->callAction('toggleDetailsView')
        ->assertSee('Vacancy Details');
});

test('the save/cancel form actions are hidden while the board view is showing', function () {
    Livewire::test(EditVacancy::class, ['record' => $this->vacancy->getRouteKey()])
        ->assertDontSee('Save changes')
        ->assertDontSee('Cancel');
});

test('the save/cancel form actions appear once switched to the details view', function () {
    Livewire::test(EditVacancy::class, ['record' => $this->vacancy->getRouteKey()])
        ->callAction('toggleDetailsView')
        ->assertSee('Save changes')
        ->assertSee('Cancel');
});
