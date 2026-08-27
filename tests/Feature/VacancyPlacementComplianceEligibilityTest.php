<?php

use App\Filament\Widgets\VacancyApplicantsTable;
use App\Filament\Widgets\VacancyMatchesTable;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\ComplianceItem;
use App\Models\ComplianceItemField;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\JobTitle;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyApplication;
use App\Models\VacancyCandidateMatch;
use App\Models\VacancyPlacement;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'generic']);
    $this->company->industries()->attach($this->industry);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->industry);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->jobTitle = JobTitle::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
    $this->item = ComplianceItem::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'DBS Check',
    ]);
    $this->field = ComplianceItemField::factory()->create(['compliance_item_id' => $this->item->id, 'data_type' => 'text']);
    $this->jobTitle->complianceItems()->attach($this->item->id, [
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $this->vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => JobStatus::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]),
    ]);
});

test('a candidate missing required compliance items cannot be marked as placed from the applicants widget', function () {
    $candidate = Candidate::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $application = VacancyApplication::create([
        'vacancy_id' => $this->vacancy->id,
        'candidate_type' => Candidate::class,
        'candidate_id' => $candidate->id,
    ]);

    Livewire::test(VacancyApplicantsTable::class, ['record' => $this->vacancy])
        ->callTableAction('markPlaced', $application, data: ['actual_salary' => 25000])
        ->assertNotified('Cannot mark as placed');

    expect(VacancyPlacement::where('vacancy_id', $this->vacancy->id)->exists())->toBeFalse();
});

test('a fully compliant candidate can be marked as placed from the applicants widget', function () {
    $candidate = Candidate::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $candidate->complianceValues()->create(['compliance_item_field_id' => $this->field->id, 'text_value' => 'filled']);

    $application = VacancyApplication::create([
        'vacancy_id' => $this->vacancy->id,
        'candidate_type' => Candidate::class,
        'candidate_id' => $candidate->id,
    ]);

    Livewire::test(VacancyApplicantsTable::class, ['record' => $this->vacancy])
        ->callTableAction('markPlaced', $application, data: ['actual_salary' => 25000]);

    expect(VacancyPlacement::where('vacancy_id', $this->vacancy->id)->where('candidate_id', $candidate->id)->exists())->toBeTrue();
});

test('an Education candidate can still be marked as placed from the applicants widget, unaffected by compliance eligibility', function () {
    $educationIndustry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($educationIndustry);
    $jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id, 'industry_id' => $educationIndustry->id]);
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $educationIndustry->id,
        'job_title_id' => $jobTitle->id,
        'job_status_id' => JobStatus::factory()->create(['company_id' => $this->company->id, 'industry_id' => $educationIndustry->id]),
    ]);

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'qualification_id' => null]);
    $application = VacancyApplication::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
    ]);

    Livewire::test(VacancyApplicantsTable::class, ['record' => $vacancy])
        ->callTableAction('markPlaced', $application, data: ['actual_salary' => 28000]);

    expect(VacancyPlacement::where('vacancy_id', $vacancy->id)->where('candidate_id', $candidate->id)->exists())->toBeTrue();
});

test('a candidate missing required compliance items cannot be marked as placed from the matches widget', function () {
    $candidate = Candidate::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $match = VacancyCandidateMatch::create([
        'vacancy_id' => $this->vacancy->id,
        'candidate_type' => Candidate::class,
        'candidate_id' => $candidate->id,
        'score' => 50,
    ]);

    Livewire::test(VacancyMatchesTable::class, ['record' => $this->vacancy])
        ->callTableAction('markPlaced', $match, data: ['actual_salary' => 25000])
        ->assertNotified('Cannot mark as placed');

    expect(VacancyPlacement::where('vacancy_id', $this->vacancy->id)->exists())->toBeFalse();
});
