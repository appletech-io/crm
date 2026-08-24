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

function assignAllTabStatus(EducationCandidate|HealthcareCandidate $candidate, Industry $industry, string $statusName): CandidateStatus
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

    return $status;
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

test('filling the education search form on the All Candidates tab still matches every consultants candidates of any status', function () {
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

    $unrelatedName = EducationCandidate::factory()->create([
        'company_id' => $this->consultant->company_id,
        'consultant_id' => $this->consultant->id,
        'first_name' => 'Robert',
    ]);
    assignAllTabStatus($unrelatedName, $industry, 'Live');

    Livewire::test(ListEducationCandidates::class)
        ->fillForm(['name' => 'Jane'])
        ->set('activeSection', 'all')
        ->call('search')
        ->assertCanSeeTableRecords([$liveOwnMatch, $liveOtherConsultant, $ownNotLive])
        ->assertCanNotSeeTableRecords([$unrelatedName]);
});

test('filling the education search form on the dedicated Search tab still restricts to own Live candidates', function () {
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
        ->set('activeSection', 'search')
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

test('filling the healthcare search form on the All Candidates tab still matches every consultants candidates of any status', function () {
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

    $unrelatedName = HealthcareCandidate::factory()->create([
        'company_id' => $this->consultant->company_id,
        'consultant_id' => $this->consultant->id,
        'first_name' => 'Robert',
    ]);
    assignAllTabStatus($unrelatedName, $industry, 'Live');

    Livewire::test(ListHealthcareCandidates::class)
        ->fillForm(['name' => 'Jane'])
        ->set('activeSection', 'all')
        ->call('search')
        ->assertCanSeeTableRecords([$liveOwnMatch, $liveOtherConsultant, $ownNotLive])
        ->assertCanNotSeeTableRecords([$unrelatedName]);
});

test('filling the healthcare search form on the dedicated Search tab still restricts to own Live candidates', function () {
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
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$liveOwnMatch])
        ->assertCanNotSeeTableRecords([$liveOtherConsultant, $ownNotLive]);
});

test('the status filter on the education All Candidates tab narrows to the selected status, across every consultant', function () {
    $industry = activateAllTabIndustry('education');

    $otherConsultant = User::factory()->create(['company_id' => $this->consultant->company_id]);

    $onboardingMatch = EducationCandidate::factory()->create(['company_id' => $this->consultant->company_id, 'consultant_id' => $otherConsultant->id]);
    $onboardingStatus = assignAllTabStatus($onboardingMatch, $industry, 'Onboarding');

    $liveNonMatch = EducationCandidate::factory()->create(['company_id' => $this->consultant->company_id, 'consultant_id' => $this->consultant->id]);
    assignAllTabStatus($liveNonMatch, $industry, 'Live');

    Livewire::test(ListEducationCandidates::class)
        ->fillForm(['status_ids' => [$onboardingStatus->id]])
        ->set('activeSection', 'all')
        ->assertFormFieldIsVisible('status_ids', 'form')
        ->call('search')
        ->assertCanSeeTableRecords([$onboardingMatch])
        ->assertCanNotSeeTableRecords([$liveNonMatch]);
});

test('the status filter on the healthcare All Candidates tab narrows to the selected status, across every consultant', function () {
    $industry = activateAllTabIndustry('healthcare');

    $otherConsultant = User::factory()->create(['company_id' => $this->consultant->company_id]);

    $onboardingMatch = HealthcareCandidate::factory()->create(['company_id' => $this->consultant->company_id, 'consultant_id' => $otherConsultant->id]);
    $onboardingStatus = assignAllTabStatus($onboardingMatch, $industry, 'Onboarding');

    $liveNonMatch = HealthcareCandidate::factory()->create(['company_id' => $this->consultant->company_id, 'consultant_id' => $this->consultant->id]);
    assignAllTabStatus($liveNonMatch, $industry, 'Live');

    Livewire::test(ListHealthcareCandidates::class)
        ->fillForm(['status_ids' => [$onboardingStatus->id]])
        ->set('activeSection', 'all')
        ->assertFormFieldIsVisible('status_ids', 'form')
        ->call('search')
        ->assertCanSeeTableRecords([$onboardingMatch])
        ->assertCanNotSeeTableRecords([$liveNonMatch]);
});
