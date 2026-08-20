<?php

use App\Filament\Pages\Analytics\VacanciesReport;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\JobTitle;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyPlacement;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('an admin can access the vacancies report', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    expect(VacanciesReport::canAccess())->toBeTrue();
});

test('a non-admin cannot access the vacancies report', function () {
    $consultant = User::factory()->create();
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    expect(VacanciesReport::canAccess())->toBeFalse();
});

test('it renders successfully and totals open and filled vacancies', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$admin->id}.active_industry", 'education');
    Cache::put("user.{$admin->id}.active_industry_id", $industry->id);

    $company = $admin->company;
    $jobTitle = JobTitle::factory()->create(['company_id' => $company->id]);
    $client = Client::factory()->create(['company_id' => $company->id, 'industry_id' => $industry->id]);

    $filledStatus = JobStatus::factory()->create([
        'company_id' => $company->id,
        'industry_id' => $industry->id,
        'is_filled_status' => true,
    ]);

    $openStatus = JobStatus::factory()->create([
        'company_id' => $company->id,
        'industry_id' => $industry->id,
        'is_filled_status' => false,
    ]);

    Vacancy::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'job_title_id' => $jobTitle->id,
        'job_status_id' => $filledStatus->id,
        'filled_at' => now(),
        'placement_fee_percentage' => 15,
        'salary_min' => 20000,
        'salary_max' => 30000,
        'positions_available' => 1,
    ]);

    Vacancy::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'job_title_id' => $jobTitle->id,
        'job_status_id' => $openStatus->id,
        'placement_fee_percentage' => 15,
        'salary_min' => 20000,
        'salary_max' => 30000,
        'positions_available' => 1,
    ]);

    $component = Livewire::test(VacanciesReport::class)->assertSuccessful();

    $stats = $component->instance()->stats();

    expect($stats['Total vacancies'])->toBe(2)
        ->and($stats['Filled'])->toBe(1)
        ->and($stats['Open'])->toBe(1);
});

test('actual margin counts a partially placed vacancy even though it is still open', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$admin->id}.active_industry", 'education');
    Cache::put("user.{$admin->id}.active_industry_id", $industry->id);

    $company = $admin->company;
    $jobTitle = JobTitle::factory()->create(['company_id' => $company->id]);
    $client = Client::factory()->create(['company_id' => $company->id, 'industry_id' => $industry->id]);

    $openStatus = JobStatus::factory()->create([
        'company_id' => $company->id,
        'industry_id' => $industry->id,
        'is_filled_status' => false,
    ]);

    // Only 2 of 3 positions placed — still sitting in an open status.
    $vacancy = Vacancy::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'job_title_id' => $jobTitle->id,
        'job_status_id' => $openStatus->id,
        'placement_fee_percentage' => 10,
        'positions_available' => 3,
    ]);

    VacancyPlacement::factory()->create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => EducationCandidate::factory()->create(['company_id' => $company->id])->id,
        'actual_salary' => 20000,
    ]);
    VacancyPlacement::factory()->create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => EducationCandidate::factory()->create(['company_id' => $company->id])->id,
        'actual_salary' => 30000,
    ]);

    $component = Livewire::test(VacanciesReport::class)->assertSuccessful();

    $stats = $component->instance()->stats();

    expect($stats['Actual margin'])->toBe('£5,000.00');
});
