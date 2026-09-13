<?php

use App\Filament\Resources\EducationCandidates\Pages\ListEducationCandidates;
use App\Filament\Resources\HealthcareCandidates\Pages\ListHealthcareCandidates;
use App\Models\CandidateCandidateStatus;
use App\Models\CandidatePool;
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

test('the education All Candidates tab has no search box, so filling the (search-tab-only) form has no effect there', function () {
    $industry = activateAllTabIndustry('education');

    $jane = EducationCandidate::factory()->create([
        'company_id' => $this->consultant->company_id,
        'consultant_id' => $this->consultant->id,
        'first_name' => 'Jane',
    ]);
    assignAllTabStatus($jane, $industry, 'Live');

    $robert = EducationCandidate::factory()->create([
        'company_id' => $this->consultant->company_id,
        'consultant_id' => $this->consultant->id,
        'first_name' => 'Robert',
    ]);
    assignAllTabStatus($robert, $industry, 'Onboarding');

    // The "name" field belongs to the dedicated Search tab's form, which
    // isn't rendered on All Candidates at all (see the page's blade) — the
    // All Candidates tab always uses EducationCandidatesTable's own native
    // filters, never this form, regardless of what's in it.
    Livewire::test(ListEducationCandidates::class)
        ->fillForm(['name' => 'Jane'])
        ->set('activeSection', 'all')
        ->assertCanSeeTableRecords([$jane, $robert]);
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

test("the education All Candidates tab's native pool filter narrows to candidates in that pool", function () {
    $industry = activateAllTabIndustry('education');

    $pool = CandidatePool::create([
        'company_id' => $this->consultant->company_id,
        'industry_id' => $industry->id,
        'user_id' => $this->consultant->id,
        'name' => 'Shortlist',
    ]);

    $inPool = EducationCandidate::factory()->create(['company_id' => $this->consultant->company_id]);
    $pool->candidatesOfType(EducationCandidate::class)->attach($inPool->id);

    $notInPool = EducationCandidate::factory()->create(['company_id' => $this->consultant->company_id]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'all')
        ->filterTable('pools', [$pool->id])
        ->assertCanSeeTableRecords([$inPool])
        ->assertCanNotSeeTableRecords([$notInPool]);
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

test('the healthcare All Candidates tab has no search box, so filling the (search-tab-only) form has no effect there', function () {
    $industry = activateAllTabIndustry('healthcare');

    $jane = HealthcareCandidate::factory()->create([
        'company_id' => $this->consultant->company_id,
        'consultant_id' => $this->consultant->id,
        'first_name' => 'Jane',
    ]);
    assignAllTabStatus($jane, $industry, 'Live');

    $robert = HealthcareCandidate::factory()->create([
        'company_id' => $this->consultant->company_id,
        'consultant_id' => $this->consultant->id,
        'first_name' => 'Robert',
    ]);
    assignAllTabStatus($robert, $industry, 'Onboarding');

    // The "name" field belongs to the dedicated Search tab's form, which
    // isn't rendered on All Candidates at all (see the page's blade) — the
    // All Candidates tab always uses HealthcareCandidatesTable's own native
    // filters, never this form, regardless of what's in it.
    Livewire::test(ListHealthcareCandidates::class)
        ->fillForm(['name' => 'Jane'])
        ->set('activeSection', 'all')
        ->assertCanSeeTableRecords([$jane, $robert]);
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

test("the education All Candidates tab's native status table filter narrows to the selected status, across every consultant", function () {
    $industry = activateAllTabIndustry('education');

    $otherConsultant = User::factory()->create(['company_id' => $this->consultant->company_id]);

    $onboardingMatch = EducationCandidate::factory()->create(['company_id' => $this->consultant->company_id, 'consultant_id' => $otherConsultant->id]);
    $onboardingStatus = assignAllTabStatus($onboardingMatch, $industry, 'Onboarding');

    $liveNonMatch = EducationCandidate::factory()->create(['company_id' => $this->consultant->company_id, 'consultant_id' => $this->consultant->id]);
    assignAllTabStatus($liveNonMatch, $industry, 'Live');

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'all')
        ->filterTable('status', [$onboardingStatus->id])
        ->assertCanSeeTableRecords([$onboardingMatch])
        ->assertCanNotSeeTableRecords([$liveNonMatch]);
});

test("the healthcare All Candidates tab's native status table filter narrows to the selected status, across every consultant", function () {
    $industry = activateAllTabIndustry('healthcare');

    $otherConsultant = User::factory()->create(['company_id' => $this->consultant->company_id]);

    $onboardingMatch = HealthcareCandidate::factory()->create(['company_id' => $this->consultant->company_id, 'consultant_id' => $otherConsultant->id]);
    $onboardingStatus = assignAllTabStatus($onboardingMatch, $industry, 'Onboarding');

    $liveNonMatch = HealthcareCandidate::factory()->create(['company_id' => $this->consultant->company_id, 'consultant_id' => $this->consultant->id]);
    assignAllTabStatus($liveNonMatch, $industry, 'Live');

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'all')
        ->filterTable('status', [$onboardingStatus->id])
        ->assertCanSeeTableRecords([$onboardingMatch])
        ->assertCanNotSeeTableRecords([$liveNonMatch]);
});

test("the healthcare All Candidates tab's native pool filter narrows to candidates in that pool", function () {
    $industry = activateAllTabIndustry('healthcare');

    $pool = CandidatePool::create([
        'company_id' => $this->consultant->company_id,
        'industry_id' => $industry->id,
        'user_id' => $this->consultant->id,
        'name' => 'Shortlist',
    ]);

    $inPool = HealthcareCandidate::factory()->create(['company_id' => $this->consultant->company_id]);
    $pool->candidatesOfType(HealthcareCandidate::class)->attach($inPool->id);

    $notInPool = HealthcareCandidate::factory()->create(['company_id' => $this->consultant->company_id]);

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'all')
        ->filterTable('pools', [$pool->id])
        ->assertCanSeeTableRecords([$inPool])
        ->assertCanNotSeeTableRecords([$notInPool]);
});
