<?php

use App\Ai\Tools\VacancyMatches;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Filament\Resources\Vacancies\VacancyResource;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyCandidateMatch;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->client = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);
});

test('it returns matches for a vacancy ordered by score', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'title' => 'Year 4 Teacher',
    ]);

    $strong = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Strong',
        'last_name' => 'Match',
    ]);
    $weak = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Weak',
        'last_name' => 'Match',
    ]);

    VacancyCandidateMatch::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $weak->id,
        'score' => 60,
    ]);
    VacancyCandidateMatch::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $strong->id,
        'score' => 95,
    ]);

    $result = (new VacancyMatches)->handle(new Request(['vacancy_title' => 'Year 4']));

    $lines = explode("\n", $result);

    expect($lines[0])->toContain('Strong Match')->toContain('95%')
        ->and($lines[1])->toContain('Weak Match')->toContain('60%');
});

test('it explains when no matches have been run for a vacancy', function () {
    Vacancy::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'title' => 'Unmatched Vacancy',
    ]);

    $result = (new VacancyMatches)->handle(new Request(['vacancy_title' => 'Unmatched']));

    expect($result)->toContain('No matches have been run')
        ->and($result)->toContain("vacancy's edit page");
});

test('it returns a message when the vacancy is not found', function () {
    $result = (new VacancyMatches)->handle(new Request(['vacancy_title' => 'Nonexistent']));

    expect($result)->toBe('No vacancy matching "Nonexistent" was found.');
});

test('it returns matching vacancies for a candidate', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'title' => 'Reception Teacher',
    ]);

    VacancyCandidateMatch::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'score' => 88,
    ]);

    $result = (new VacancyMatches)->handle(new Request(['candidate_name' => 'Jane']));

    expect($result)->toContain('Reception Teacher')
        ->and($result)->toContain($this->client->name)
        ->and($result)->toContain('88%');
});

test('it resolves a candidate by their full first and last name together', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Darrick',
        'last_name' => 'Greenholt',
    ]);

    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'title' => 'Senior Software Engineer',
    ]);

    VacancyCandidateMatch::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'score' => 82,
    ]);

    $result = (new VacancyMatches)->handle(new Request(['candidate_name' => 'Darrick Greenholt']));

    expect($result)->toContain('Senior Software Engineer')->toContain('82%');
});

test('it links candidates and vacancies to their edit pages', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'title' => 'Year 4 Teacher',
    ]);

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Strong',
        'last_name' => 'Match',
    ]);

    VacancyCandidateMatch::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'score' => 95,
    ]);

    $byVacancy = (new VacancyMatches)->handle(new Request(['vacancy_title' => 'Year 4']));
    $byCandidate = (new VacancyMatches)->handle(new Request(['candidate_name' => 'Strong']));

    $candidateUrl = EducationCandidateResource::getUrl('edit', ['record' => $candidate]);
    $vacancyUrl = VacancyResource::getUrl('edit', ['record' => $vacancy]);

    expect($byVacancy)->toContain("[Strong Match]({$candidateUrl})")
        ->and($byCandidate)->toContain("[Year 4 Teacher]({$vacancyUrl})");
});

test('it asks for a vacancy or candidate when neither is provided', function () {
    $result = (new VacancyMatches)->handle(new Request([]));

    expect($result)->toBe('Tell me either a vacancy title or a candidate name to look up matches for.');
});

test('it does not return matches from a different industry', function () {
    $otherIndustry = Industry::factory()->create();
    $otherClient = Client::factory()->create(['industry_id' => $otherIndustry->id]);

    Vacancy::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $otherClient->id,
        'title' => 'Other Industry Vacancy',
    ]);

    $result = (new VacancyMatches)->handle(new Request(['vacancy_title' => 'Other Industry Vacancy']));

    expect($result)->toBe('No vacancy matching "Other Industry Vacancy" was found.');
});
