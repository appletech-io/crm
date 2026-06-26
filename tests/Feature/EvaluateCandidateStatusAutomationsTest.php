<?php

use App\Actions\Candidates\ChangeCandidateStatus;
use App\Actions\Candidates\CheckCandidateStatusAutomations;
use App\Enums\ActivityType;
use App\Models\CandidateSkill;
use App\Models\CandidateStatus;
use App\Models\CandidateStatusAutomation;
use App\Models\EducationCandidate;
use App\Models\Industry;
use Illuminate\Support\Facades\Queue;
use Lorisleiva\Actions\Decorators\JobDecorator;

beforeEach(function () {
    Queue::fake();

    $this->industry = Industry::factory()->create();

    $this->fromStatus = CandidateStatus::factory()->create([
        'industry_id' => $this->industry->id,
        'name' => 'Application Sent',
    ]);

    $this->toStatus = CandidateStatus::factory()->create([
        'industry_id' => $this->industry->id,
        'name' => 'Onboarding',
    ]);
});

test('moves candidate to next status when all required fields are filled', function () {
    $candidate = EducationCandidate::factory()->create([
        'first_name' => 'Jane',
        'email' => 'jane@example.com',
        'postcode' => 'SW1A 1AA',
    ]);

    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'completed_fields' => ['first_name', 'email', 'postcode'],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
    expect($candidate->statuses()->where('candidate_status_id', $this->fromStatus->id)->exists())->toBeFalse();
});

test('does not move candidate when required fields are missing', function () {
    $candidate = EducationCandidate::factory()->create([
        'first_name' => 'Jane',
        'email' => null,
    ]);

    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'completed_fields' => ['first_name', 'email'],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->fromStatus->id)->exists())->toBeTrue();
    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();
});

test('does nothing when candidate has no statuses', function () {
    $candidate = EducationCandidate::factory()->create();

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'completed_fields' => ['first_name'],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->count())->toBe(0);
});

test('can dispatch as a queued job', function () {
    $candidate = EducationCandidate::factory()->create();

    CheckCandidateStatusAutomations::dispatch($candidate);

    Queue::assertPushed(JobDecorator::class, fn ($job) => $job->getAction() instanceof CheckCandidateStatusAutomations);
});

test('moves candidate via relationship wildcard when relation has records', function () {
    $candidate = EducationCandidate::factory()->create(['first_name' => 'Jane']);

    $skill = CandidateSkill::factory()->create([
        'company_id' => $candidate->company_id,
        'industry_id' => $this->industry->id,
    ]);
    $candidate->skills()->attach($skill);

    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'completed_fields' => ['first_name', 'skills.*'],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
});

test('ChangeCandidateStatus logs a status automation activity', function () {
    $candidate = EducationCandidate::factory()->create(['first_name' => 'Jane']);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    $automation = CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'completed_fields' => ['first_name'],
    ]);

    ChangeCandidateStatus::run($candidate, $automation);

    $activity = $candidate->activities()->first();

    expect($activity)->not->toBeNull();
    expect($activity->type)->toBe(ActivityType::StatusAutomation);

    $body = json_decode($activity->body, true);
    expect($body['from'])->toBe('Application Sent');
    expect($body['to'])->toBe('Onboarding');
    expect($body['required_fields'])->toBe(['first_name']);
    expect($body['snapshot'])->toHaveKey('first_name');
});

test('observer triggers automation check when candidate is updated', function () {
    $candidate = EducationCandidate::factory()->create(['first_name' => 'Jane', 'email' => null]);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'completed_fields' => ['first_name', 'email'],
    ]);

    // Automation should not fire yet — email is missing
    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();

    // Filling in the missing field via a model update should trigger the automation
    $candidate->update(['email' => 'jane@example.com']);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
});

test('CheckCandidateStatusAutomations logs activity when status changes', function () {
    $candidate = EducationCandidate::factory()->create(['first_name' => 'Jane', 'email' => 'jane@example.com']);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'completed_fields' => ['first_name', 'email'],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->activities()->where('type', ActivityType::StatusAutomation->value)->exists())->toBeTrue();
});
