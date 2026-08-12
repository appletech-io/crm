<?php

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Vacancies\VacancyResource;
use App\Filament\Widgets\ClientPipelineOverview;
use App\Models\Client;
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
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->company = $this->user->company;
    $this->industry = Industry::factory()->create(['slug' => 'education']);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->client = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $this->jobStatus = JobStatus::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id, 'name' => 'Open']);
});

test('the widget renders for a client', function () {
    Livewire::test(ClientPipelineOverview::class, ['record' => $this->client])
        ->assertSuccessful();
});

test('it lists the jobs linked to this client, and excludes jobs for other clients', function () {
    $ours = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
        'title' => 'Year 3 Class Teacher',
    ]);

    $otherClient = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $notOurs = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $otherClient->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
    ]);

    Livewire::test(ClientPipelineOverview::class, ['record' => $this->client])
        ->assertCanSeeTableRecords([$ours])
        ->assertCanNotSeeTableRecords([$notOurs])
        ->assertSee('Year 3 Class Teacher');
});

test('the row links through to the vacancy edit page', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
    ]);

    $html = Livewire::test(ClientPipelineOverview::class, ['record' => $this->client])->html();

    expect($html)->toContain(e(VacancyResource::getUrl('edit', ['record' => $vacancy])));
});

test('the pipeline tab renders on the client edit page', function () {
    Livewire::test(EditClient::class, ['record' => $this->client->id])
        ->assertSuccessful()
        ->assertSee('Pipeline');
});

test('estimated placement value averages the salary range, applies the fee percentage, and multiplies by positions available', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
        'salary_min' => 30000,
        'salary_max' => 40000,
        'placement_fee_percentage' => 15,
        'positions_available' => 2,
    ]);

    // Midpoint 35000 * 15% * 2 positions = 10500
    expect($vacancy->estimatedPlacementValue())->toBe(10500.0);
});

test('estimated placement value falls back to whichever salary bound is set', function () {
    $vacancy = Vacancy::factory()->create([
        'salary_min' => null,
        'salary_max' => 40000,
        'placement_fee_percentage' => 10,
        'positions_available' => 1,
    ]);

    expect($vacancy->estimatedPlacementValue())->toBe(4000.0);
});

test('estimated placement value is null when the fee percentage is not set', function () {
    $vacancy = Vacancy::factory()->create([
        'salary_min' => 30000,
        'salary_max' => 40000,
        'placement_fee_percentage' => null,
    ]);

    expect($vacancy->estimatedPlacementValue())->toBeNull();
});

test('estimated placement value is null when no salary is set', function () {
    $vacancy = Vacancy::factory()->create([
        'salary_min' => null,
        'salary_max' => null,
        'placement_fee_percentage' => 15,
    ]);

    expect($vacancy->estimatedPlacementValue())->toBeNull();
});

test('the widget shows an em dash rather than a wrong figure when a job has no fee percentage set', function () {
    Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
        'salary_min' => 30000,
        'salary_max' => 40000,
        'placement_fee_percentage' => null,
    ]);

    Livewire::test(ClientPipelineOverview::class, ['record' => $this->client])
        ->assertSeeText('—');
});

test('the widget shows an empty state when the client has no jobs', function () {
    Livewire::test(ClientPipelineOverview::class, ['record' => $this->client])
        ->assertSee('No jobs for this client yet');
});
