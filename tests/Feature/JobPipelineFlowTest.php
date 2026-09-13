<?php

use App\Filament\Resources\CandidatePools\CandidatePoolResource;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Vacancies\VacancyResource;
use App\Filament\Widgets\JobPipelineFlow;
use App\Models\Candidate;
use App\Models\CandidatePool;
use App\Models\Client;
use App\Models\Company;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyPlacement;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'it']);
    $this->company->industries()->attach($this->industry->id, ['uses_bookings' => false]);

    $this->admin = User::factory()->create(['company_id' => $this->company->id]);
    $this->admin->industries()->attach($this->industry);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    Cache::put("user.{$this->admin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $this->industry->id);
});

test('the widget renders successfully', function () {
    Livewire::test(JobPipelineFlow::class)->assertSuccessful();
});

test('the status segments are ordered by sort_order and show open/total vacancy counts', function () {
    $filled = JobStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'sort_order' => 1,
    ]);
    $open = JobStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'sort_order' => 0,
    ]);

    $openVacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_status_id' => $open->id,
        'positions_available' => 1,
    ]);

    $filledVacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_status_id' => $filled->id,
        'positions_available' => 1,
    ]);

    VacancyPlacement::factory()->create([
        'vacancy_id' => $filledVacancy->id,
        'candidate_type' => Candidate::class,
        'candidate_id' => Candidate::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id])->id,
        'placed_at' => now(),
    ]);

    $widget = new JobPipelineFlow;
    $segments = $widget->statuses();

    expect($segments->pluck('status.id')->all())->toBe([$open->id, $filled->id])
        ->and($segments[0]['open'])->toBe(1)
        ->and($segments[0]['total'])->toBe(1)
        ->and($segments[1]['open'])->toBe(0)
        ->and($segments[1]['total'])->toBe(1)
        ->and($segments[0]['url'])->toBe(VacancyResource::getUrl('index', [
            'tableFilters' => ['job_status_id' => ['value' => $open->id]],
        ]));
});

test('a vacancy from another industry does not contribute to the status counts', function () {
    $status = JobStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $otherIndustry = Industry::factory()->create(['slug' => 'construction']);
    $otherIndustryStatus = JobStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $otherIndustry->id,
    ]);

    Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $otherIndustry->id,
        'job_status_id' => $otherIndustryStatus->id,
    ]);

    $widget = new JobPipelineFlow;

    expect($widget->statuses()->pluck('status.id')->all())->toBe([$status->id]);
});

test('the consultant filter, set via the shared dashboard-consultant-changed event, narrows the counts', function () {
    $status = JobStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $consultant = User::factory()->create(['company_id' => $this->company->id]);
    $otherConsultant = User::factory()->create(['company_id' => $this->company->id]);

    Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_status_id' => $status->id,
        'consultant_id' => $consultant->id,
    ]);
    Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_status_id' => $status->id,
        'consultant_id' => $otherConsultant->id,
    ]);

    Livewire::test(JobPipelineFlow::class)
        ->dispatch('dashboard-consultant-changed', consultantId: $consultant->id)
        ->assertSet('consultantId', $consultant->id);

    $widget = new JobPipelineFlow;
    $widget->consultantId = $consultant->id;

    expect($widget->statuses()->first()['total'])->toBe(1)
        ->and($widget->jobsCount())->toBe(1);
});

test('the jobs segment counts every vacancy in the active industry and links to the vacancy list', function () {
    Vacancy::factory()->count(3)->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $widget = new JobPipelineFlow;

    expect($widget->jobsCount())->toBe(3)
        ->and($widget->jobsUrl())->toBe(VacancyResource::getUrl('index'));
});

test('with no pool selected, the candidates segment counts every candidate in the active industry', function () {
    Candidate::factory()->count(2)->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $widget = new JobPipelineFlow;

    expect($widget->candidatesCount())->toBe(2)
        ->and($widget->candidatesUrl())->toBe(CandidateResource::getUrl('index'));
});

test('selecting a pool scopes the candidates segment to that pool and links to it', function () {
    $pool = CandidatePool::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'user_id' => $this->admin->id,
        'name' => 'Shortlisted',
    ]);

    $inPool = Candidate::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $inPool->candidatePools()->attach($pool->id);

    Candidate::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    $widget = new JobPipelineFlow;
    $widget->poolId = $pool->id;

    expect($widget->candidatesCount())->toBe(1)
        ->and($widget->candidatesUrl())->toBe(CandidatePoolResource::getUrl('edit', ['record' => $pool->id]));
});

test('picking a pool from the dropdown switches to the candidates view, so the effect is visible', function () {
    $pool = CandidatePool::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'user_id' => $this->admin->id,
        'name' => 'Shortlisted',
    ]);

    Livewire::test(JobPipelineFlow::class)
        ->assertSet('viewingCandidates', false)
        ->set('poolId', $pool->id)
        ->assertSet('viewingCandidates', true);
});

test('selecting the candidates step shows candidates inline and clears the status selection', function () {
    $status = JobStatus::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $candidate = Candidate::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    $component = Livewire::test(JobPipelineFlow::class)
        ->call('selectStatus', $status->id)
        ->assertSet('selectedStatusId', $status->id)
        ->call('selectCandidates')
        ->assertSet('viewingCandidates', true);

    expect($component->instance()->selectedCandidates()->pluck('id')->all())->toBe([$candidate->id]);
});

test('choosing a status step again switches back off the candidates view', function () {
    $status = JobStatus::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    Livewire::test(JobPipelineFlow::class)
        ->call('selectCandidates')
        ->assertSet('viewingCandidates', true)
        ->call('selectStatus', $status->id)
        ->assertSet('viewingCandidates', false)
        ->assertSet('selectedStatusId', $status->id);
});

test('with no pool selected, the inline candidates list shows every candidate in the active industry', function () {
    Candidate::factory()->count(2)->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    $widget = new JobPipelineFlow;

    expect($widget->selectedCandidates())->toHaveCount(2);
});

test('selecting a pool scopes the inline candidates list to that pool', function () {
    $pool = CandidatePool::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'user_id' => $this->admin->id,
        'name' => 'Shortlisted',
    ]);

    $inPool = Candidate::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $inPool->candidatePools()->attach($pool->id);

    Candidate::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    $widget = new JobPipelineFlow;
    $widget->poolId = $pool->id;

    expect($widget->selectedCandidates()->pluck('id')->all())->toBe([$inPool->id]);
});

test('selecting a per-client pool also narrows the jobs count and list to that client', function () {
    $blueWave = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $otherClient = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    $pool = CandidatePool::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'client_id' => $blueWave->id,
        'user_id' => $this->admin->id,
        'name' => 'BlueWave Digital Candidates',
    ]);

    $blueWaveVacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'client_id' => $blueWave->id,
    ]);

    Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'client_id' => $otherClient->id,
    ]);

    $widget = new JobPipelineFlow;
    $widget->poolId = $pool->id;

    expect($widget->jobsCount())->toBe(1)
        ->and($widget->selectedJobs()->pluck('id')->all())->toBe([$blueWaveVacancy->id]);
});

test('selecting a per-client pool also narrows each status\'s open/total counts to that client', function () {
    $blueWave = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $otherClient = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    $pool = CandidatePool::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'client_id' => $blueWave->id,
        'user_id' => $this->admin->id,
        'name' => 'BlueWave Digital Candidates',
    ]);

    $status = JobStatus::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'client_id' => $blueWave->id,
        'job_status_id' => $status->id,
    ]);

    Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'client_id' => $otherClient->id,
        'job_status_id' => $status->id,
    ]);

    $widget = new JobPipelineFlow;
    $widget->poolId = $pool->id;

    expect($widget->statuses()->first()['total'])->toBe(1);
});

test('a pool with no client leaves the jobs and status counts unfiltered', function () {
    $pool = CandidatePool::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'user_id' => $this->admin->id,
        'name' => 'Cloud Specialists',
    ]);

    Vacancy::factory()->count(2)->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $widget = new JobPipelineFlow;
    $widget->poolId = $pool->id;

    expect($widget->jobsCount())->toBe(2);
});

test('with no status selected, the inline jobs list shows every vacancy in the active industry', function () {
    Vacancy::factory()->count(2)->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $widget = new JobPipelineFlow;

    expect($widget->selectedStatusId)->toBeNull()
        ->and($widget->selectedJobs())->toHaveCount(2)
        ->and($widget->selectedJobsCount())->toBe(2)
        ->and($widget->selectedJobsUrl())->toBe(VacancyResource::getUrl('index'));
});

test('selecting a status filters the inline jobs list to just that status', function () {
    $open = JobStatus::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $filled = JobStatus::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    $openVacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_status_id' => $open->id,
    ]);
    Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_status_id' => $filled->id,
    ]);

    $component = Livewire::test(JobPipelineFlow::class)
        ->call('selectStatus', $open->id)
        ->assertSet('selectedStatusId', $open->id);

    expect($component->instance()->selectedJobs()->pluck('id')->all())->toBe([$openVacancy->id])
        ->and($component->instance()->selectedJobsCount())->toBe(1)
        ->and($component->instance()->selectedJobsUrl())->toBe(VacancyResource::getUrl('index', [
            'tableFilters' => ['job_status_id' => ['value' => $open->id]],
        ]));
});

test('selecting the jobs step clears the status filter', function () {
    $status = JobStatus::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    Livewire::test(JobPipelineFlow::class)
        ->call('selectStatus', $status->id)
        ->assertSet('selectedStatusId', $status->id)
        ->call('selectStatus', null)
        ->assertSet('selectedStatusId', null);
});

test('the pool options only include pools visible to the current user', function () {
    $ownPool = CandidatePool::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'user_id' => $this->admin->id,
        'name' => 'Mine',
    ]);

    $otherUser = User::factory()->create(['company_id' => $this->company->id]);
    CandidatePool::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'user_id' => $otherUser->id,
        'name' => 'Not mine',
        'company_pool' => false,
    ]);

    $widget = new JobPipelineFlow;

    expect($widget->poolOptions())->toBe([$ownPool->id => 'Mine']);
});
