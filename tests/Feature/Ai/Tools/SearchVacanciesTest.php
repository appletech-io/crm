<?php

use App\Ai\Tools\SearchVacancies;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Vacancies\VacancyResource;
use App\Models\Client;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\JobTitle;
use App\Models\User;
use App\Models\Vacancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create();
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('it returns vacancies matching the client name filter', function () {
    $client = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Riverside School',
    ]);

    $jobTitle = JobTitle::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Teacher']);
    $status = JobStatus::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Open']);

    Vacancy::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'job_title_id' => $jobTitle->id,
        'job_status_id' => $status->id,
        'title' => 'Year 4 Teacher',
    ]);

    $result = (new SearchVacancies)->handle(new Request(['client_name' => 'Riverside']));

    expect($result)->toContain('Year 4 Teacher')
        ->and($result)->toContain('Riverside School')
        ->and($result)->toContain('Teacher')
        ->and($result)->toContain('Open');
});

test('it filters by job title', function () {
    $client = Client::factory()->create(['company_id' => $this->user->company_id, 'industry_id' => $this->industry->id]);
    $teacherTitle = JobTitle::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Teacher']);
    $taTitle = JobTitle::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Teaching Assistant']);

    Vacancy::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'job_title_id' => $teacherTitle->id,
        'title' => 'Teacher role',
    ]);
    Vacancy::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'job_title_id' => $taTitle->id,
        'title' => 'TA role',
    ]);

    $result = (new SearchVacancies)->handle(new Request(['job_title' => 'Teaching Assistant']));

    expect($result)->toContain('TA role')
        ->and($result)->not->toContain('Teacher role');
});

test('it filters by status', function () {
    $client = Client::factory()->create(['company_id' => $this->user->company_id, 'industry_id' => $this->industry->id]);
    $open = JobStatus::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Open']);
    $closed = JobStatus::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Closed']);

    Vacancy::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'job_status_id' => $open->id,
        'title' => 'Open role',
    ]);
    Vacancy::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'job_status_id' => $closed->id,
        'title' => 'Closed role',
    ]);

    $result = (new SearchVacancies)->handle(new Request(['status' => 'Open']));

    expect($result)->toContain('Open role')
        ->and($result)->not->toContain('Closed role');
});

test('it filters by region matched against the client address', function () {
    $leicester = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'city' => 'Leicester',
    ]);
    $manchester = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'city' => 'Manchester',
    ]);

    Vacancy::factory()->create(['company_id' => $this->user->company_id, 'client_id' => $leicester->id, 'title' => 'Leicester role']);
    Vacancy::factory()->create(['company_id' => $this->user->company_id, 'client_id' => $manchester->id, 'title' => 'Manchester role']);

    $result = (new SearchVacancies)->handle(new Request(['region' => 'Leicester']));

    expect($result)->toContain('Leicester role')
        ->and($result)->not->toContain('Manchester role');
});

test('it links the vacancy and client to their edit pages', function () {
    $client = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Riverside School',
    ]);

    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'title' => 'Year 4 Teacher',
    ]);

    $result = (new SearchVacancies)->handle(new Request(['client_name' => 'Riverside']));

    $vacancyUrl = VacancyResource::getUrl('edit', ['record' => $vacancy]);
    $clientUrl = ClientResource::getUrl('edit', ['record' => $client]);

    expect($result)->toContain("[Year 4 Teacher]({$vacancyUrl})")
        ->and($result)->toContain("[Riverside School]({$clientUrl})");
});

test('it paginates results and reports how many more match', function () {
    $client = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Paginated School',
    ]);

    Vacancy::factory()->count(51)->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
    ]);

    $firstPage = (new SearchVacancies)->handle(new Request(['client_name' => 'Paginated School']));

    expect($firstPage)->toContain('Showing 50 of 51 — 1 more match. Ask to see the next 50 to continue.');

    $secondPage = (new SearchVacancies)->handle(new Request(['client_name' => 'Paginated School', 'offset' => 50]));

    expect($secondPage)->not->toContain('more match');
});

test('it returns a plain message when nothing matches', function () {
    $result = (new SearchVacancies)->handle(new Request(['client_name' => 'Nonexistent']));

    expect($result)->toBe('No vacancies matched.');
});

test('it does not return vacancies belonging to a different industry', function () {
    $otherIndustry = Industry::factory()->create();
    $otherClient = Client::factory()->create(['industry_id' => $otherIndustry->id]);

    Vacancy::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $otherClient->id,
        'title' => 'Other Industry Vacancy',
    ]);

    $result = (new SearchVacancies)->handle(new Request(['client_name' => 'Other Industry Vacancy']));

    expect($result)->toBe('No vacancies matched.');
});

test('a non-admin only sees their own vacancies', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $client = Client::factory()->create(['company_id' => $this->user->company_id, 'industry_id' => $this->industry->id]);

    Vacancy::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'consultant_id' => $consultant->id,
        'title' => 'My vacancy',
    ]);
    Vacancy::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'consultant_id' => $this->user->id,
        'title' => 'Someone elses vacancy',
    ]);

    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);
    $this->actingAs($consultant);

    $result = (new SearchVacancies)->handle(new Request([]));

    expect($result)->toContain('My vacancy')
        ->and($result)->not->toContain('Someone elses vacancy');
});
