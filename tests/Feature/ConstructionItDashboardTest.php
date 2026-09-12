<?php

use App\Enums\ActivityType;
use App\Enums\VacancyEmploymentType;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\ConsultantPerformanceSummary;
use App\Filament\Widgets\EducationConsultantLeaderboard;
use App\Filament\Widgets\GenericConsultantKpiOverview;
use App\Filament\Widgets\ItPlacementsOverview;
use App\Models\Candidate;
use App\Models\CandidateActivity;
use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyApplication;
use App\Models\VacancyPlacement;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->construction = Industry::factory()->create(['slug' => 'construction']);
    $this->it = Industry::factory()->create(['slug' => 'it']);
    $this->company->industries()->attach([$this->construction->id, $this->it->id]);

    $this->admin = User::factory()->create(['company_id' => $this->company->id]);
    $this->admin->industries()->attach([$this->construction->id, $this->it->id]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->setActiveIndustry = function (Industry $industry): void {
        Cache::put("user.{$this->admin->id}.active_industry", $industry->slug);
        Cache::put("user.{$this->admin->id}.active_industry_id", $industry->id);
    };
});

test('the dashboard resolves ConstructionDashboard for the construction industry, reusing the booking-based widgets', function () {
    ($this->setActiveIndustry)($this->construction);

    $dashboard = new Dashboard;

    expect($dashboard->getWidgets())->toBe([
        ConsultantPerformanceSummary::class,
        GenericConsultantKpiOverview::class,
        EducationConsultantLeaderboard::class,
    ]);
});

test('the dashboard resolves ItDashboard for the it industry, with no booking-based widgets', function () {
    ($this->setActiveIndustry)($this->it);

    $dashboard = new Dashboard;

    expect($dashboard->getWidgets())->toBe([
        ItPlacementsOverview::class,
        GenericConsultantKpiOverview::class,
    ])
        ->and($dashboard->getWidgets())->not->toContain(ConsultantPerformanceSummary::class)
        ->and($dashboard->getWidgets())->not->toContain(EducationConsultantLeaderboard::class);
});

test('the construction dashboard renders successfully for an admin', function () {
    ($this->setActiveIndustry)($this->construction);

    Livewire::test(Dashboard::class)->assertSuccessful();
});

test('the it dashboard renders successfully for an admin', function () {
    ($this->setActiveIndustry)($this->it);

    Livewire::test(Dashboard::class)->assertSuccessful();
});

test('the generic consultant kpi overview scopes activity counts to the active industry, even though construction and IT share the Candidate model', function () {
    $constructionCandidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->construction->id,
    ]);
    $itCandidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->it->id,
    ]);

    CandidateActivity::create([
        'model_type' => Candidate::class,
        'model_id' => $constructionCandidate->id,
        'user_id' => $this->admin->id,
        'type' => ActivityType::Call->value,
        'note' => 'Checked in',
    ]);
    CandidateActivity::create([
        'model_type' => Candidate::class,
        'model_id' => $itCandidate->id,
        'user_id' => $this->admin->id,
        'type' => ActivityType::Call->value,
        'note' => 'Checked in',
    ]);

    ($this->setActiveIndustry)($this->it);

    $widget = new GenericConsultantKpiOverview;

    expect($widget->monthStats()['calls'])->toBe(1);
});

test('it placements overview counts open vacancies and this month\'s applications/placements, excluding temp roles from the fee pipeline', function () {
    ($this->setActiveIndustry)($this->it);

    $openPermanent = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->it->id,
        'employment_type' => VacancyEmploymentType::Permanent,
        'positions_available' => 1,
        'salary_min' => 40000,
        'salary_max' => 40000,
        'placement_fee_percentage' => 20,
    ]);

    $openTemp = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->it->id,
        'employment_type' => VacancyEmploymentType::Temp,
        'positions_available' => 1,
    ]);

    $filledPermanent = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->it->id,
        'employment_type' => VacancyEmploymentType::Permanent,
        'positions_available' => 1,
        'salary_min' => 50000,
        'salary_max' => 50000,
        'placement_fee_percentage' => 20,
    ]);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->it->id,
    ]);

    VacancyPlacement::factory()->create([
        'vacancy_id' => $filledPermanent->id,
        'candidate_type' => Candidate::class,
        'candidate_id' => $candidate->id,
        'placed_at' => now(),
    ]);

    VacancyApplication::create([
        'vacancy_id' => $openPermanent->id,
        'candidate_type' => Candidate::class,
        'candidate_id' => $candidate->id,
    ]);

    // A vacancy from a different industry must never contribute to IT's figures.
    Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->construction->id,
        'employment_type' => VacancyEmploymentType::Permanent,
        'positions_available' => 1,
        'salary_min' => 100000,
        'salary_max' => 100000,
        'placement_fee_percentage' => 50,
    ]);

    $widget = new ItPlacementsOverview;
    $stats = $widget->pipelineStats();

    expect($stats['openVacancies'])->toBe(2)
        ->and($stats['applications'])->toBe(1)
        ->and($stats['placements'])->toBe(1)
        // Only openPermanent's estimate (£40,000 * 20%) — openTemp is excluded
        // and filledPermanent isn't open.
        ->and($stats['feePipeline'])->toBe(8000.0);
});
