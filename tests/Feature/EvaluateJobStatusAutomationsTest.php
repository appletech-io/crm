<?php

use App\Actions\Jobs\ChangeJobStatus;
use App\Actions\Jobs\CheckJobStatusAutomations;
use App\Console\Commands\CheckTimeBasedJobStatusAutomations;
use App\Enums\ActivityType;
use App\Models\CandidateSkill;
use App\Models\Client;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\JobStatusAutomation;
use App\Models\JobTitle;
use App\Models\Vacancy;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Lorisleiva\Actions\Decorators\JobDecorator;

/**
 * @param  array<int, string>  $fields
 * @return array<int, array{field: string, operator: string}>
 */
function jobFilledConditions(array $fields): array
{
    return collect($fields)
        ->map(fn (string $field): array => ['field' => $field, 'operator' => 'filled'])
        ->all();
}

beforeEach(function () {
    Queue::fake();

    $this->industry = Industry::factory()->create();
    $this->client = Client::factory()->create(['industry_id' => $this->industry->id]);
    $this->jobTitle = JobTitle::factory()->create(['industry_id' => $this->industry->id]);

    $this->fromStatus = JobStatus::factory()->create([
        'industry_id' => $this->industry->id,
        'name' => 'Open',
    ]);

    $this->toStatus = JobStatus::factory()->create([
        'industry_id' => $this->industry->id,
        'name' => 'Filled',
    ]);
});

/** @param  array<string, mixed>  $attributes */
function makeJobVacancy(Client $client, JobTitle $jobTitle, JobStatus $fromStatus, array $attributes = []): Vacancy
{
    return Vacancy::factory()->create([
        'client_id' => $client->id,
        'job_title_id' => $jobTitle->id,
        'job_status_id' => $fromStatus->id,
        ...$attributes,
    ]);
}

test('moves vacancy to next status when all required fields are filled', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus, ['title' => 'Year 3 Teacher', 'description' => 'Full time role']);

    JobStatusAutomation::factory()->create([
        'job_status_id' => $this->fromStatus->id,
        'to_job_status_id' => $this->toStatus->id,
        'conditions' => jobFilledConditions(['title', 'description']),
    ]);

    CheckJobStatusAutomations::run($vacancy);

    expect($vacancy->refresh()->job_status_id)->toBe($this->toStatus->id);
});

test('does not move vacancy when required fields are missing', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus, ['title' => 'Year 3 Teacher', 'description' => null]);

    JobStatusAutomation::factory()->create([
        'job_status_id' => $this->fromStatus->id,
        'to_job_status_id' => $this->toStatus->id,
        'conditions' => jobFilledConditions(['title', 'description']),
    ]);

    CheckJobStatusAutomations::run($vacancy);

    expect($vacancy->refresh()->job_status_id)->toBe($this->fromStatus->id);
});

test('can dispatch as a queued job', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus);

    CheckJobStatusAutomations::dispatch($vacancy);

    Queue::assertPushed(JobDecorator::class, fn ($job) => $job->getAction() instanceof CheckJobStatusAutomations);
});

test('moves vacancy via relationship wildcard when relation has records', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus, ['title' => 'Year 3 Teacher']);

    $skill = CandidateSkill::factory()->create([
        'company_id' => $vacancy->company_id,
        'industry_id' => $this->industry->id,
    ]);
    $vacancy->skills()->attach($skill);

    JobStatusAutomation::factory()->create([
        'job_status_id' => $this->fromStatus->id,
        'to_job_status_id' => $this->toStatus->id,
        'conditions' => jobFilledConditions(['title', 'skills.*']),
    ]);

    CheckJobStatusAutomations::run($vacancy);

    expect($vacancy->refresh()->job_status_id)->toBe($this->toStatus->id);
});

test('moves vacancy when an equals condition matches', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus, ['title' => 'Year 3 Teacher']);

    JobStatusAutomation::factory()->create([
        'job_status_id' => $this->fromStatus->id,
        'to_job_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'title', 'operator' => 'equals', 'value' => 'Year 3 Teacher'],
        ],
    ]);

    CheckJobStatusAutomations::run($vacancy);

    expect($vacancy->refresh()->job_status_id)->toBe($this->toStatus->id);
});

test('does not move vacancy when an equals condition does not match', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus, ['title' => 'Year 3 Teacher']);

    JobStatusAutomation::factory()->create([
        'job_status_id' => $this->fromStatus->id,
        'to_job_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'title', 'operator' => 'equals', 'value' => 'Nursery Practitioner'],
        ],
    ]);

    CheckJobStatusAutomations::run($vacancy);

    expect($vacancy->refresh()->job_status_id)->toBe($this->fromStatus->id);
});

test('an equals condition can combine with a filled condition', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus, ['title' => 'Year 3 Teacher', 'description' => null]);

    JobStatusAutomation::factory()->create([
        'job_status_id' => $this->fromStatus->id,
        'to_job_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'title', 'operator' => 'equals', 'value' => 'Year 3 Teacher'],
            ['field' => 'description', 'operator' => 'filled'],
        ],
    ]);

    CheckJobStatusAutomations::run($vacancy);

    expect($vacancy->refresh()->job_status_id)->toBe($this->fromStatus->id);

    $vacancy->update(['description' => 'Full time role']);

    expect($vacancy->refresh()->job_status_id)->toBe($this->toStatus->id);
});

test('moves vacancy when a contains condition matches a substring', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus, ['description' => 'Immediate start, supply cover needed']);

    JobStatusAutomation::factory()->create([
        'job_status_id' => $this->fromStatus->id,
        'to_job_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'description', 'operator' => 'contains', 'value' => 'supply'],
        ],
    ]);

    CheckJobStatusAutomations::run($vacancy);

    expect($vacancy->refresh()->job_status_id)->toBe($this->toStatus->id);
});

test('does not move vacancy when a contains condition does not match', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus, ['description' => 'Immediate start, supply cover needed']);

    JobStatusAutomation::factory()->create([
        'job_status_id' => $this->fromStatus->id,
        'to_job_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'description', 'operator' => 'contains', 'value' => 'permanent'],
        ],
    ]);

    CheckJobStatusAutomations::run($vacancy);

    expect($vacancy->refresh()->job_status_id)->toBe($this->fromStatus->id);
});

test('moves vacancy when a days_since_at_least condition is satisfied', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus, ['filled_at' => now()->subDays(40)]);

    JobStatusAutomation::factory()->create([
        'job_status_id' => $this->fromStatus->id,
        'to_job_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'filled_at', 'operator' => 'days_since_at_least', 'value' => '30'],
        ],
    ]);

    CheckJobStatusAutomations::run($vacancy);

    expect($vacancy->refresh()->job_status_id)->toBe($this->toStatus->id);
});

test('does not move vacancy when a days_since_at_least condition is not yet satisfied', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus, ['filled_at' => now()->subDays(10)]);

    JobStatusAutomation::factory()->create([
        'job_status_id' => $this->fromStatus->id,
        'to_job_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'filled_at', 'operator' => 'days_since_at_least', 'value' => '30'],
        ],
    ]);

    CheckJobStatusAutomations::run($vacancy);

    expect($vacancy->refresh()->job_status_id)->toBe($this->fromStatus->id);
});

test('a days_since_at_least condition never matches when the date field is empty', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus, ['filled_at' => null]);

    JobStatusAutomation::factory()->create([
        'job_status_id' => $this->fromStatus->id,
        'to_job_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'filled_at', 'operator' => 'days_since_at_least', 'value' => '30'],
        ],
    ]);

    CheckJobStatusAutomations::run($vacancy);

    expect($vacancy->refresh()->job_status_id)->toBe($this->fromStatus->id);
});

test('moves vacancy when a days_until_at_most condition is satisfied', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus, ['filled_at' => now()->addDays(10)]);

    JobStatusAutomation::factory()->create([
        'job_status_id' => $this->fromStatus->id,
        'to_job_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'filled_at', 'operator' => 'days_until_at_most', 'value' => '30'],
        ],
    ]);

    CheckJobStatusAutomations::run($vacancy);

    expect($vacancy->refresh()->job_status_id)->toBe($this->toStatus->id);
});

test('does not move vacancy when a days_until_at_most condition is not yet satisfied', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus, ['filled_at' => now()->addDays(90)]);

    JobStatusAutomation::factory()->create([
        'job_status_id' => $this->fromStatus->id,
        'to_job_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'filled_at', 'operator' => 'days_until_at_most', 'value' => '30'],
        ],
    ]);

    CheckJobStatusAutomations::run($vacancy);

    expect($vacancy->refresh()->job_status_id)->toBe($this->fromStatus->id);
});

test('soft-deleting a vacancy triggers the job status automation check via the deleted_at field', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus, ['title' => 'Year 3 Teacher']);

    JobStatusAutomation::factory()->create([
        'job_status_id' => $this->fromStatus->id,
        'to_job_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'deleted_at', 'operator' => 'filled'],
        ],
    ]);

    expect($vacancy->refresh()->job_status_id)->toBe($this->fromStatus->id);

    $vacancy->delete();

    expect($vacancy->refresh()->job_status_id)->toBe($this->toStatus->id);
});

test('an equals condition on a wildcard relation path never matches', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus, ['title' => 'Year 3 Teacher']);

    $skill = CandidateSkill::factory()->create([
        'company_id' => $vacancy->company_id,
        'industry_id' => $this->industry->id,
    ]);
    $vacancy->skills()->attach($skill);

    JobStatusAutomation::factory()->create([
        'job_status_id' => $this->fromStatus->id,
        'to_job_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'skills.*', 'operator' => 'equals', 'value' => 'anything'],
        ],
    ]);

    CheckJobStatusAutomations::run($vacancy);

    expect($vacancy->refresh()->job_status_id)->toBe($this->fromStatus->id);
});

test('ChangeJobStatus logs a status automation activity', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus, ['title' => 'Year 3 Teacher']);

    $automation = JobStatusAutomation::factory()->create([
        'job_status_id' => $this->fromStatus->id,
        'to_job_status_id' => $this->toStatus->id,
        'conditions' => jobFilledConditions(['title']),
    ]);

    ChangeJobStatus::run($vacancy, $automation);

    $activity = $vacancy->activities()->first();

    expect($activity)->not->toBeNull();
    expect($activity->type)->toBe(ActivityType::StatusAutomation);

    $body = json_decode($activity->body, true);
    expect($body['from'])->toBe('Open');
    expect($body['to'])->toBe('Filled');
    expect($body['conditions'])->toBe(jobFilledConditions(['title']));
    expect($body['snapshot'])->toHaveKey('title');
});

test('observer triggers automation check when vacancy is updated', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus, ['title' => 'Year 3 Teacher', 'description' => null]);

    JobStatusAutomation::factory()->create([
        'job_status_id' => $this->fromStatus->id,
        'to_job_status_id' => $this->toStatus->id,
        'conditions' => jobFilledConditions(['title', 'description']),
    ]);

    // Automation should not fire yet — description is missing
    expect($vacancy->refresh()->job_status_id)->toBe($this->fromStatus->id);

    // Filling in the missing field via a model update should trigger the automation
    $vacancy->update(['description' => 'Full time role']);

    expect($vacancy->refresh()->job_status_id)->toBe($this->toStatus->id);
});

test('CheckJobStatusAutomations logs activity when status changes', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus, ['title' => 'Year 3 Teacher']);

    JobStatusAutomation::factory()->create([
        'job_status_id' => $this->fromStatus->id,
        'to_job_status_id' => $this->toStatus->id,
        'conditions' => jobFilledConditions(['title']),
    ]);

    CheckJobStatusAutomations::run($vacancy);

    expect($vacancy->activities()->where('type', ActivityType::StatusAutomation->value)->exists())->toBeTrue();
});

test('the time-based command moves vacancies whose days_since_at_least condition has since become satisfied', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus, ['filled_at' => now()->subDays(40)]);

    JobStatusAutomation::factory()->create([
        'job_status_id' => $this->fromStatus->id,
        'to_job_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'filled_at', 'operator' => 'days_since_at_least', 'value' => '30'],
        ],
    ]);

    Artisan::call(CheckTimeBasedJobStatusAutomations::class);

    expect($vacancy->refresh()->job_status_id)->toBe($this->toStatus->id);
});

test('the time-based command moves vacancies whose days_until_at_most condition has since become satisfied', function () {
    $vacancy = makeJobVacancy($this->client, $this->jobTitle, $this->fromStatus, ['filled_at' => now()->addDays(10)]);

    JobStatusAutomation::factory()->create([
        'job_status_id' => $this->fromStatus->id,
        'to_job_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'filled_at', 'operator' => 'days_until_at_most', 'value' => '30'],
        ],
    ]);

    Artisan::call(CheckTimeBasedJobStatusAutomations::class);

    expect($vacancy->refresh()->job_status_id)->toBe($this->toStatus->id);
});
