<?php

use App\Enums\VacancyEmploymentType;
use App\Filament\Widgets\Reports\TopClientsByPlacementsTable;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\JobTitle;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyPlacement;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'it']);
    Cache::put("user.{$this->user->id}.active_industry", 'it');
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->company = $this->user->company;
    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id]);
    $this->openStatus = JobStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
});

function placeCandidateAt(User $user, JobTitle $jobTitle, JobStatus $status, Client $client, string $placedAt, float $actualSalary = 25000): VacancyPlacement
{
    $vacancy = Vacancy::factory()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'industry_id' => $client->industry_id,
        'job_title_id' => $jobTitle->id,
        'job_status_id' => $status->id,
        'placement_fee_percentage' => 20,
        'positions_available' => 1,
        'employment_type' => VacancyEmploymentType::Permanent->value,
    ]);

    return VacancyPlacement::factory()->create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => Candidate::class,
        'candidate_id' => Candidate::factory()->create(['company_id' => $user->company_id, 'industry_id' => $client->industry_id])->id,
        'actual_salary' => $actualSalary,
        'placed_at' => $placedAt,
    ]);
}

test('rows resolves client names and orders by placement fee value, highest first', function () {
    $bigClient = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id, 'name' => 'Big Co']);
    $smallClient = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id, 'name' => 'Small Co']);

    placeCandidateAt($this->user, $this->jobTitle, $this->openStatus, $smallClient, now()->toDateString(), 20000);
    placeCandidateAt($this->user, $this->jobTitle, $this->openStatus, $bigClient, now()->toDateString(), 60000);

    $widget = new TopClientsByPlacementsTable;
    $widget->pageFilters = [
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
    ];

    $rows = $widget->rows();

    expect($rows->first()['clientName'])->toBe('Big Co')
        ->and($rows->first()['value'])->toBe(12000.0)
        ->and($rows->last()['clientName'])->toBe('Small Co');
});
