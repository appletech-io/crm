<?php

use App\Jobs\MatchCandidatesToVacancy;
use App\Models\CandidateSkill;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\Vacancy;
use App\Models\VacancyApplication;
use App\Models\VacancyCandidateMatch;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Creating a Client with a postcode triggers a real (synchronous, in
    // tests) geocoding lookup — faked here so it fails harmlessly and
    // doesn't touch the explicit lat/lng set on fixtures below.
    Http::fake();

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'postcode' => null,
    ]);
    $this->vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
    ]);
});

test('matching against all candidates scores every candidate in the company pool, and excludes other companies', function () {
    $skill = CandidateSkill::factory()->create(['company_id' => $this->company->id]);
    $this->vacancy->skills()->attach($skill->id);

    $goodMatch = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $goodMatch->skills()->attach($skill->id);

    // Missing the required skill (and any location signal) is a real,
    // scoreable 0% skill match — not "unscoreable" — but it's below the
    // minimum score, so it's excluded like an unscoreable candidate would be.
    $noMatchingSkills = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'latitude' => null,
        'longitude' => null,
    ]);

    $otherCompany = Company::factory()->create();
    $outsideCompany = EducationCandidate::factory()->create(['company_id' => $otherCompany->id]);
    $outsideCompany->skills()->attach($skill->id);

    MatchCandidatesToVacancy::dispatchSync($this->vacancy->id);

    expect(VacancyCandidateMatch::where('vacancy_id', $this->vacancy->id)->where('candidate_id', $goodMatch->id)->value('score'))->toBe(100);
    expect(VacancyCandidateMatch::where('vacancy_id', $this->vacancy->id)->where('candidate_id', $noMatchingSkills->id)->exists())->toBeFalse();
    expect(VacancyCandidateMatch::where('vacancy_id', $this->vacancy->id)->where('candidate_id', $outsideCompany->id)->exists())->toBeFalse();
});

test('a score at or below the 50% minimum does not get a match row', function () {
    $skillA = CandidateSkill::factory()->create(['company_id' => $this->company->id]);
    $skillB = CandidateSkill::factory()->create(['company_id' => $this->company->id]);
    $this->vacancy->skills()->attach([$skillA->id, $skillB->id]);

    $borderlineCandidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $borderlineCandidate->skills()->attach($skillA->id);

    MatchCandidatesToVacancy::dispatchSync($this->vacancy->id);

    expect(VacancyCandidateMatch::where('vacancy_id', $this->vacancy->id)->where('candidate_id', $borderlineCandidate->id)->exists())->toBeFalse();
});

test('a match that falls back to or below the minimum score on a re-run is removed', function () {
    $skillA = CandidateSkill::factory()->create(['company_id' => $this->company->id]);
    $skillB = CandidateSkill::factory()->create(['company_id' => $this->company->id]);
    $this->vacancy->skills()->attach([$skillA->id, $skillB->id]);

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $candidate->skills()->attach([$skillA->id, $skillB->id]);

    MatchCandidatesToVacancy::dispatchSync($this->vacancy->id);
    expect(VacancyCandidateMatch::where('candidate_id', $candidate->id)->value('score'))->toBe(100);

    $candidate->skills()->detach($skillB->id);
    MatchCandidatesToVacancy::dispatchSync($this->vacancy->id);

    expect(VacancyCandidateMatch::where('vacancy_id', $this->vacancy->id)->where('candidate_id', $candidate->id)->exists())->toBeFalse();
});

test('a re-run clears matches for candidates who have left the pool entirely, not just re-scored ones', function () {
    $skill = CandidateSkill::factory()->create(['company_id' => $this->company->id]);
    $this->vacancy->skills()->attach($skill->id);

    $staysInPool = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $staysInPool->skills()->attach($skill->id);

    $leavesPool = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $leavesPool->skills()->attach($skill->id);

    MatchCandidatesToVacancy::dispatchSync($this->vacancy->id);
    expect(VacancyCandidateMatch::where('vacancy_id', $this->vacancy->id)->count())->toBe(2);

    // Moving to another company (or deletion) takes them out of the query
    // this job scans — the per-row loop alone would never revisit them.
    $otherCompany = Company::factory()->create();
    $leavesPool->update(['company_id' => $otherCompany->id]);

    MatchCandidatesToVacancy::dispatchSync($this->vacancy->id);

    expect(VacancyCandidateMatch::where('vacancy_id', $this->vacancy->id)->where('candidate_id', $staysInPool->id)->exists())->toBeTrue();
    expect(VacancyCandidateMatch::where('vacancy_id', $this->vacancy->id)->where('candidate_id', $leavesPool->id)->exists())->toBeFalse();
});

test('a candidate with no scoreable signal at all is skipped rather than given a match row', function () {
    // No skills required on the vacancy and no coordinates on the candidate
    // means neither scorer has anything to go on.
    $unscoreable = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'latitude' => null,
        'longitude' => null,
    ]);

    MatchCandidatesToVacancy::dispatchSync($this->vacancy->id);

    expect(VacancyCandidateMatch::where('vacancy_id', $this->vacancy->id)->where('candidate_id', $unscoreable->id)->exists())->toBeFalse();
});

test('matching does not touch the vacancy_applications table at all', function () {
    $skill = CandidateSkill::factory()->create(['company_id' => $this->company->id]);
    $this->vacancy->skills()->attach($skill->id);

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $candidate->skills()->attach($skill->id);

    MatchCandidatesToVacancy::dispatchSync($this->vacancy->id);

    expect(VacancyCandidateMatch::where('vacancy_id', $this->vacancy->id)->count())->toBe(1);
    expect(VacancyApplication::where('vacancy_id', $this->vacancy->id)->count())->toBe(0);
});

test('running the match again updates existing scores instead of duplicating rows', function () {
    $skillA = CandidateSkill::factory()->create(['company_id' => $this->company->id]);
    $skillB = CandidateSkill::factory()->create(['company_id' => $this->company->id]);
    $skillC = CandidateSkill::factory()->create(['company_id' => $this->company->id]);
    $this->vacancy->skills()->attach([$skillA->id, $skillB->id, $skillC->id]);

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $candidate->skills()->attach([$skillA->id, $skillB->id]);

    MatchCandidatesToVacancy::dispatchSync($this->vacancy->id);
    expect(VacancyCandidateMatch::where('vacancy_id', $this->vacancy->id)->count())->toBe(1);
    expect(VacancyCandidateMatch::where('candidate_id', $candidate->id)->value('score'))->toBe(67);

    $candidate->skills()->attach($skillC->id);
    MatchCandidatesToVacancy::dispatchSync($this->vacancy->id);

    expect(VacancyCandidateMatch::where('vacancy_id', $this->vacancy->id)->count())->toBe(1);
    expect(VacancyCandidateMatch::where('candidate_id', $candidate->id)->value('score'))->toBe(100);
});

test('matching a vacancy that no longer exists by the time the job runs is a no-op rather than an error', function () {
    $vacancyId = $this->vacancy->id;
    $this->vacancy->delete();

    MatchCandidatesToVacancy::dispatchSync($vacancyId);

    expect(VacancyCandidateMatch::where('vacancy_id', $vacancyId)->exists())->toBeFalse();
});

test('a client-less temp vacancy still matches on skills, just without a location signal', function () {
    $generalCoverVacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => null,
        'industry_id' => $this->industry->id,
    ]);

    $skill = CandidateSkill::factory()->create(['company_id' => $this->company->id]);
    $generalCoverVacancy->skills()->attach($skill->id);

    $goodMatch = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $goodMatch->skills()->attach($skill->id);

    MatchCandidatesToVacancy::dispatchSync($generalCoverVacancy->id);

    expect(VacancyCandidateMatch::where('vacancy_id', $generalCoverVacancy->id)->where('candidate_id', $goodMatch->id)->value('score'))->toBe(100);
});
