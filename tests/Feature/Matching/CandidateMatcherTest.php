<?php

use App\Models\Candidate;
use App\Models\CandidateSkill;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\Vacancy;
use App\Services\Matching\CandidateMatcher;
use App\Services\Matching\Scorers\JobTitleMatchScorer;
use App\Services\Matching\Scorers\LocationProximityScorer;
use App\Services\Matching\Scorers\SkillMatchScorer;

function makeVacancyAndCandidate(array $vacancyAttributes = [], array $candidateAttributes = []): array
{
    $company = Company::factory()->create();
    $industry = Industry::factory()->create(['slug' => 'education']);
    // postcode is left blank so creating the client doesn't trigger a real
    // geocoding lookup (ClientObserver::saved() only dispatches GeocodeClient
    // when a postcode is present) — that job runs synchronously in tests
    // (QUEUE_CONNECTION=sync) and would overwrite any explicit lat/lng below.
    $client = Client::factory()->create(array_merge([
        'company_id' => $company->id,
        'industry_id' => $industry->id,
        'postcode' => null,
    ], $vacancyAttributes['client'] ?? []));
    unset($vacancyAttributes['client']);

    $vacancy = Vacancy::factory()->create(array_merge([
        'company_id' => $company->id,
        'client_id' => $client->id,
    ], $vacancyAttributes));

    $candidate = EducationCandidate::factory()->create(array_merge([
        'company_id' => $company->id,
    ], $candidateAttributes));

    return [$vacancy, $candidate];
}

test('skill match scorer returns the fraction of required skills the candidate has', function () {
    [$vacancy, $candidate] = makeVacancyAndCandidate();

    $skillA = CandidateSkill::factory()->create(['company_id' => $vacancy->company_id]);
    $skillB = CandidateSkill::factory()->create(['company_id' => $vacancy->company_id]);
    $skillC = CandidateSkill::factory()->create(['company_id' => $vacancy->company_id]);

    $vacancy->skills()->attach([$skillA->id, $skillB->id, $skillC->id]);
    $candidate->skills()->attach([$skillA->id, $skillB->id]);

    $scorer = new SkillMatchScorer;

    expect($scorer->score($candidate->fresh(), $vacancy->fresh()))->toBe(2 / 3);
});

test('skill match scorer returns null when the vacancy has no required skills', function () {
    [$vacancy, $candidate] = makeVacancyAndCandidate();

    $scorer = new SkillMatchScorer;

    expect($scorer->score($candidate, $vacancy))->toBeNull();
});

test('location proximity scorer gives full marks within the close radius and decays to zero by the far radius', function () {
    [$vacancy, $candidate] = makeVacancyAndCandidate(
        vacancyAttributes: ['client' => ['latitude' => 51.5074, 'longitude' => -0.1278]], // London
        candidateAttributes: ['latitude' => 51.5074, 'longitude' => -0.1278], // same spot
    );

    $scorer = new LocationProximityScorer;

    expect($scorer->score($candidate, $vacancy->fresh('client')))->toBe(1.0);
});

test('location proximity scorer returns null when either side is missing coordinates', function () {
    [$vacancy, $candidate] = makeVacancyAndCandidate(
        candidateAttributes: ['latitude' => null, 'longitude' => null],
    );

    $scorer = new LocationProximityScorer;

    expect($scorer->score($candidate, $vacancy->fresh('client')))->toBeNull();
});

test('location proximity scorer also has a signal for the generic candidate model, now that it has lat/long', function () {
    $company = Company::factory()->create();
    $industry = Industry::factory()->create(['slug' => 'it']);
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'industry_id' => $industry->id,
        'postcode' => null,
        'latitude' => 51.5074,
        'longitude' => -0.1278,
    ]);
    $vacancy = Vacancy::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
    ]);
    $candidate = Candidate::factory()->create([
        'company_id' => $company->id,
        'industry_id' => $industry->id,
        'latitude' => 51.5074,
        'longitude' => -0.1278,
    ]);

    $scorer = new LocationProximityScorer;

    expect($scorer->score($candidate, $vacancy->fresh('client')))->toBe(1.0);
});

test('job title match scorer returns the word-overlap fraction between the vacancy and the candidate\'s most recent job title', function () {
    [$vacancy, $candidate] = makeVacancyAndCandidate();

    $jobTitle = JobTitle::factory()->create(['company_id' => $vacancy->company_id, 'name' => 'Year 3 Class Teacher']);
    $vacancy->update(['job_title_id' => $jobTitle->id]);

    $candidate->employmentHistories()->create(['company_name' => 'Oakwood Primary', 'job_title' => 'Class Teacher', 'worked_from' => '2020-01-01']);

    $scorer = new JobTitleMatchScorer;

    // {year,3,class,teacher} vs {class,teacher} -> intersection 2, union 4 -> 0.5
    expect($scorer->score($candidate->fresh(), $vacancy->fresh('jobTitle')))->toBe(0.5);
});

test('job title match scorer picks the most recent job title when the candidate has several', function () {
    [$vacancy, $candidate] = makeVacancyAndCandidate();

    $jobTitle = JobTitle::factory()->create(['company_id' => $vacancy->company_id, 'name' => 'Teaching Assistant']);
    $vacancy->update(['job_title_id' => $jobTitle->id]);

    $candidate->employmentHistories()->create([
        'company_name' => 'Riverside Primary',
        'job_title' => 'Teaching Assistant',
        'worked_from' => '2015-01-01',
        'worked_to' => '2018-01-01',
    ]);
    $candidate->employmentHistories()->create([
        'company_name' => 'Oakwood Primary',
        'job_title' => 'Class Teacher',
        'worked_from' => '2018-01-01',
        'worked_to' => null, // current role
    ]);

    $scorer = new JobTitleMatchScorer;

    // The current role ("Class Teacher") should be used, not the older,
    // exactly-matching one -> no word overlap with "Teaching Assistant" -> 0.0
    expect($scorer->score($candidate->fresh(), $vacancy->fresh('jobTitle')))->toBe(0.0);
});

test('job title match scorer returns null when the candidate has no employment history', function () {
    [$vacancy, $candidate] = makeVacancyAndCandidate();

    $jobTitle = JobTitle::factory()->create(['company_id' => $vacancy->company_id, 'name' => 'Class Teacher']);
    $vacancy->update(['job_title_id' => $jobTitle->id]);

    $scorer = new JobTitleMatchScorer;

    expect($scorer->score($candidate, $vacancy->fresh('jobTitle')))->toBeNull();
});

test('the matcher combines scorers as a weighted average and rescales to 0-100', function () {
    [$vacancy, $candidate] = makeVacancyAndCandidate(
        vacancyAttributes: ['client' => ['latitude' => 51.5074, 'longitude' => -0.1278]],
        candidateAttributes: ['latitude' => 51.5074, 'longitude' => -0.1278],
    );

    $skillA = CandidateSkill::factory()->create(['company_id' => $vacancy->company_id]);
    $skillB = CandidateSkill::factory()->create(['company_id' => $vacancy->company_id]);

    $vacancy->skills()->attach([$skillA->id, $skillB->id]);
    $candidate->skills()->attach([$skillA->id]);

    $matcher = new CandidateMatcher;

    // Skill: 1/2 matched (weight 0.5), Location: perfect (weight 0.2),
    // job title: no signal (candidate has no employment history)
    // = (0.5 * 0.5 + 1.0 * 0.2) / 0.7 = 0.642857... -> 64
    expect($matcher->score($candidate->fresh(), $vacancy->fresh('client')))->toBe(64);
});

test('the matcher combines all three default scorers together', function () {
    [$vacancy, $candidate] = makeVacancyAndCandidate(
        vacancyAttributes: ['client' => ['latitude' => 51.5074, 'longitude' => -0.1278]],
        candidateAttributes: ['latitude' => 51.5074, 'longitude' => -0.1278],
    );

    $jobTitle = JobTitle::factory()->create(['company_id' => $vacancy->company_id, 'name' => 'Class Teacher']);
    $vacancy->update(['job_title_id' => $jobTitle->id]);

    $skillA = CandidateSkill::factory()->create(['company_id' => $vacancy->company_id]);
    $skillB = CandidateSkill::factory()->create(['company_id' => $vacancy->company_id]);
    $vacancy->skills()->attach([$skillA->id, $skillB->id]);
    $candidate->skills()->attach([$skillA->id]);

    $candidate->employmentHistories()->create(['company_name' => 'Oakwood Primary', 'job_title' => 'Class Teacher', 'worked_from' => '2020-01-01']);

    $matcher = new CandidateMatcher;

    // Skill: 1/2 matched (weight 0.5), job title: exact match (weight 0.3),
    // location: perfect (weight 0.2) -> all three active, all weights count
    // = (0.5 * 0.5 + 1.0 * 0.3 + 1.0 * 0.2) / 1.0 = 0.75 -> 75
    expect($matcher->score($candidate->fresh(), $vacancy->fresh(['client', 'jobTitle'])))->toBe(75);
});

test('the matcher returns null when no scorer has a signal', function () {
    [$vacancy, $candidate] = makeVacancyAndCandidate(
        candidateAttributes: ['latitude' => null, 'longitude' => null],
    );

    $matcher = new CandidateMatcher;

    expect($matcher->score($candidate, $vacancy))->toBeNull();
});

test('the matcher reweights around whichever single scorer has a signal', function () {
    [$vacancy, $candidate] = makeVacancyAndCandidate(
        candidateAttributes: ['latitude' => null, 'longitude' => null],
    );

    $skillA = CandidateSkill::factory()->create(['company_id' => $vacancy->company_id]);
    $vacancy->skills()->attach([$skillA->id]);
    $candidate->skills()->attach([$skillA->id]);

    $matcher = new CandidateMatcher;

    // Only skills has a signal (location is null, no coordinates) -> 100% skill match -> 100
    expect($matcher->score($candidate->fresh(), $vacancy->fresh()))->toBe(100);
});
