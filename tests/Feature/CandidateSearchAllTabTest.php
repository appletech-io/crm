<?php

use App\Filament\Resources\EducationCandidates\Pages\ListEducationCandidates;
use App\Filament\Resources\HealthcareCandidates\Pages\ListHealthcareCandidates;
use App\Models\CandidateCandidateStatus;
use App\Models\CandidateStatus;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->consultant = User::factory()->create();
    $this->consultant->assignRole('consultant');
    $this->actingAs($this->consultant);
});

function activateAllTabIndustry(string $slug): Industry
{
    $industry = Industry::factory()->create(['slug' => $slug]);

    Cache::put('user.'.test()->consultant->id.'.active_industry', $industry->slug);
    Cache::put('user.'.test()->consultant->id.'.active_industry_id', $industry->id);

    return $industry;
}

function assignAllTabStatus(EducationCandidate|HealthcareCandidate $candidate, Industry $industry, string $statusName): void
{
    $status = CandidateStatus::factory()->create([
        'company_id' => $candidate->company_id,
        'industry_id' => $industry->id,
        'name' => $statusName,
    ]);

    CandidateCandidateStatus::create([
        'model_type' => $candidate::class,
        'model_id' => $candidate->id,
        'candidate_status_id' => $status->id,
    ]);
}

test('the education All Candidates tab shows every consultants candidates by default, unfiltered', function () {
    $industry = activateAllTabIndustry('education');

    $ownCandidate = EducationCandidate::factory()->create([
        'company_id' => $this->consultant->company_id,
        'consultant_id' => $this->consultant->id,
    ]);

    $otherConsultant = User::factory()->create(['company_id' => $this->consultant->company_id]);
    $otherCandidate = EducationCandidate::factory()->create([
        'company_id' => $this->consultant->company_id,
        'consultant_id' => $otherConsultant->id,
    ]);
    assignAllTabStatus($otherCandidate, $industry, 'Onboarding');

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'all')
        ->assertCanSeeTableRecords([$ownCandidate, $otherCandidate]);
});

test('filling the education search form on the All Candidates tab narrows to the search scope', function () {
    $industry = activateAllTabIndustry('education');

    $liveOwnMatch = EducationCandidate::factory()->create([
        'company_id' => $this->consultant->company_id,
        'consultant_id' => $this->consultant->id,
        'first_name' => 'Jane',
    ]);
    assignAllTabStatus($liveOwnMatch, $industry, 'Live');

    $otherConsultant = User::factory()->create(['company_id' => $this->consultant->company_id]);
    $liveOtherConsultant = EducationCandidate::factory()->create([
        'company_id' => $this->consultant->company_id,
        'consultant_id' => $otherConsultant->id,
        'first_name' => 'Jane',
    ]);
    assignAllTabStatus($liveOtherConsultant, $industry, 'Live');

    $ownNotLive = EducationCandidate::factory()->create([
        'company_id' => $this->consultant->company_id,
        'consultant_id' => $this->consultant->id,
        'first_name' => 'Jane',
    ]);
    assignAllTabStatus($ownNotLive, $industry, 'Onboarding');

    Livewire::test(ListEducationCandidates::class)
        ->fillForm(['name' => 'Jane'])
        ->set('activeSection', 'all')
        ->call('search')
        ->assertCanSeeTableRecords([$liveOwnMatch])
        ->assertCanNotSeeTableRecords([$liveOtherConsultant, $ownNotLive]);
});

test('the healthcare All Candidates tab shows every consultants candidates by default, unfiltered', function () {
    $industry = activateAllTabIndustry('healthcare');

    $ownCandidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->consultant->company_id,
        'consultant_id' => $this->consultant->id,
    ]);

    $otherConsultant = User::factory()->create(['company_id' => $this->consultant->company_id]);
    $otherCandidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->consultant->company_id,
        'consultant_id' => $otherConsultant->id,
    ]);
    assignAllTabStatus($otherCandidate, $industry, 'Onboarding');

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'all')
        ->assertCanSeeTableRecords([$ownCandidate, $otherCandidate]);
});

test('filling the healthcare search form on the All Candidates tab narrows to the search scope', function () {
    $industry = activateAllTabIndustry('healthcare');

    $liveOwnMatch = HealthcareCandidate::factory()->create([
        'company_id' => $this->consultant->company_id,
        'consultant_id' => $this->consultant->id,
        'first_name' => 'Jane',
    ]);
    assignAllTabStatus($liveOwnMatch, $industry, 'Live');

    $otherConsultant = User::factory()->create(['company_id' => $this->consultant->company_id]);
    $liveOtherConsultant = HealthcareCandidate::factory()->create([
        'company_id' => $this->consultant->company_id,
        'consultant_id' => $otherConsultant->id,
        'first_name' => 'Jane',
    ]);
    assignAllTabStatus($liveOtherConsultant, $industry, 'Live');

    $ownNotLive = HealthcareCandidate::factory()->create([
        'company_id' => $this->consultant->company_id,
        'consultant_id' => $this->consultant->id,
        'first_name' => 'Jane',
    ]);
    assignAllTabStatus($ownNotLive, $industry, 'Onboarding');

    Livewire::test(ListHealthcareCandidates::class)
        ->fillForm(['name' => 'Jane'])
        ->set('activeSection', 'all')
        ->call('search')
        ->assertCanSeeTableRecords([$liveOwnMatch])
        ->assertCanNotSeeTableRecords([$liveOtherConsultant, $ownNotLive]);
});
