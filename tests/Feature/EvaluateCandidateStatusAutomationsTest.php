<?php

use App\Actions\Candidates\ChangeCandidateStatus;
use App\Actions\Candidates\CheckCandidateStatusAutomations;
use App\Enums\ActivityType;
use App\Models\Booking;
use App\Models\CandidateSkill;
use App\Models\CandidateStatus;
use App\Models\CandidateStatusAutomation;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Lorisleiva\Actions\Decorators\JobDecorator;

/**
 * @param  array<int, string>  $fields
 * @return array<int, array{field: string, operator: string}>
 */
function filledConditions(array $fields): array
{
    return collect($fields)
        ->map(fn (string $field): array => ['field' => $field, 'operator' => 'filled'])
        ->all();
}

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
        'conditions' => filledConditions(['first_name', 'email', 'postcode']),
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
        'conditions' => filledConditions(['first_name', 'email']),
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
        'conditions' => filledConditions(['first_name']),
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
        'conditions' => filledConditions(['first_name', 'skills.*']),
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
});

test('moves candidate when an equals condition matches', function () {
    $candidate = EducationCandidate::factory()->create(['first_name' => 'Jane']);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'first_name', 'operator' => 'equals', 'value' => 'Jane'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
});

test('does not move candidate when an equals condition does not match', function () {
    $candidate = EducationCandidate::factory()->create(['first_name' => 'Jane']);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'first_name', 'operator' => 'equals', 'value' => 'John'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();
});

test('an equals condition can combine with a filled condition', function () {
    $candidate = EducationCandidate::factory()->create(['first_name' => 'Jane', 'email' => null]);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'first_name', 'operator' => 'equals', 'value' => 'Jane'],
            ['field' => 'email', 'operator' => 'filled'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();

    $candidate->update(['email' => 'jane@example.com']);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
});

test('moves candidate when a not_equals condition does not match the value', function () {
    $candidate = EducationCandidate::factory()->create(['first_name' => 'Jane']);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'first_name', 'operator' => 'not_equals', 'value' => 'John'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
});

test('does not move candidate when a not_equals condition matches the value', function () {
    $candidate = EducationCandidate::factory()->create(['first_name' => 'Jane']);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'first_name', 'operator' => 'not_equals', 'value' => 'Jane'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();
});

test('moves candidate when a contains condition matches a substring', function () {
    $candidate = EducationCandidate::factory()->create(['notes' => 'Available for supply work immediately']);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'notes', 'operator' => 'contains', 'value' => 'supply'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
});

test('does not move candidate when a contains condition does not match', function () {
    $candidate = EducationCandidate::factory()->create(['notes' => 'Available for supply work immediately']);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'notes', 'operator' => 'contains', 'value' => 'permanent'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();
});

test('moves candidate when a before condition matches a date field', function () {
    $candidate = EducationCandidate::factory()->create(['available_from' => '2026-01-01']);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'available_from', 'operator' => 'before', 'value' => '2026-06-01'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
});

test('moves candidate when an after condition matches a date field', function () {
    $candidate = EducationCandidate::factory()->create(['available_from' => '2026-06-01']);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'available_from', 'operator' => 'after', 'value' => '2026-01-01'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
});

test('does not move candidate when a before condition does not hold', function () {
    $candidate = EducationCandidate::factory()->create(['available_from' => '2026-12-01']);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'available_from', 'operator' => 'before', 'value' => '2026-06-01'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();
});

test('moves candidate when a days_since_at_least condition is satisfied', function () {
    $candidate = EducationCandidate::factory()->create(['available_from' => now()->subDays(40)->toDateString()]);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'available_from', 'operator' => 'days_since_at_least', 'value' => '30'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
});

test('does not move candidate when a days_since_at_least condition is not yet satisfied', function () {
    $candidate = EducationCandidate::factory()->create(['available_from' => now()->subDays(10)->toDateString()]);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'available_from', 'operator' => 'days_since_at_least', 'value' => '30'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();
});

test('a days_since_at_least condition never matches when the date field is empty', function () {
    $candidate = EducationCandidate::factory()->create(['available_from' => null]);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'available_from', 'operator' => 'days_since_at_least', 'value' => '30'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();
});

test('moves candidate to offline when their last booking ended more than 3 months ago', function () {
    $candidate = EducationCandidate::factory()->create();
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    $client = Client::factory()->create(['company_id' => $candidate->company_id]);
    Booking::factory()->create([
        'company_id' => $candidate->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'start_date' => now()->subMonths(4)->toDateString(),
        'end_date' => now()->subMonths(4)->addDays(3)->toDateString(),
    ]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'last_booking_date', 'operator' => 'days_since_at_least', 'value' => '90'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
});

test('does not move candidate to offline when their last booking was within 3 months', function () {
    $candidate = EducationCandidate::factory()->create();
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    $client = Client::factory()->create(['company_id' => $candidate->company_id]);
    Booking::factory()->create([
        'company_id' => $candidate->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->subMonth()->addDays(3)->toDateString(),
    ]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'last_booking_date', 'operator' => 'days_since_at_least', 'value' => '90'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();
});

test('does not move a candidate to offline who has never had a booking', function () {
    $candidate = EducationCandidate::factory()->create();
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'last_booking_date', 'operator' => 'days_since_at_least', 'value' => '90'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();
});

test('a blank condition matches a candidate with no bookings at all', function () {
    $candidate = EducationCandidate::factory()->create();
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'last_booking_date', 'operator' => 'blank'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
});

test('a blank condition does not match a candidate who has a last booking date', function () {
    $candidate = EducationCandidate::factory()->create();
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    $client = Client::factory()->create(['company_id' => $candidate->company_id]);
    Booking::factory()->create([
        'company_id' => $candidate->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->subMonth()->addDays(3)->toDateString(),
    ]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'last_booking_date', 'operator' => 'blank'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();
});

test('a blank condition on a wildcard relation matches when the relation has no records', function () {
    $candidate = EducationCandidate::factory()->create(['first_name' => 'Jane']);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'skills.*', 'operator' => 'blank'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
});

test('a blank condition on a wildcard relation does not match once the relation has a record', function () {
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
        'conditions' => [
            ['field' => 'skills.*', 'operator' => 'blank'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();
});

test('a candidate who never booked but was recently marked compliance-complete is not yet moved offline, only once 90 days have passed', function () {
    $candidate = EducationCandidate::factory()->create(['compliance_completed_at' => now()->subDays(10)]);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'last_booking_date', 'operator' => 'blank'],
            ['field' => 'compliance_completed_at', 'operator' => 'days_since_at_least', 'value' => '90'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();

    $candidate->update(['compliance_completed_at' => now()->subDays(100)]);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
});

test('moves candidate when a days_until_at_most condition is satisfied', function () {
    $candidate = EducationCandidate::factory()->create(['available_from' => now()->addDays(10)->toDateString()]);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'available_from', 'operator' => 'days_until_at_most', 'value' => '30'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
});

test('does not move candidate when a days_until_at_most condition is not yet satisfied', function () {
    $candidate = EducationCandidate::factory()->create(['available_from' => now()->addDays(90)->toDateString()]);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'available_from', 'operator' => 'days_until_at_most', 'value' => '30'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();
});

test('a days_until_at_most condition never matches when the date field is empty', function () {
    $candidate = EducationCandidate::factory()->create(['available_from' => null]);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'available_from', 'operator' => 'days_until_at_most', 'value' => '30'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();
});

test('moves candidate to the same target status when only one of two automations for that pair is satisfied', function () {
    $candidate = EducationCandidate::factory()->create([
        'dbs_expiry_date' => now()->subDay()->toDateString(), // expired
        'right_to_work_expiry_date' => now()->addYear()->toDateString(), // not expired
    ]);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'dbs_expiry_date', 'operator' => 'before', 'value' => now()->toDateString()],
        ],
    ]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'right_to_work_expiry_date', 'operator' => 'before', 'value' => now()->toDateString()],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
});

test('does not move candidate when neither of two automations for the same pair is satisfied', function () {
    $candidate = EducationCandidate::factory()->create([
        'dbs_expiry_date' => now()->addYear()->toDateString(),
        'right_to_work_expiry_date' => now()->addYear()->toDateString(),
    ]);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'dbs_expiry_date', 'operator' => 'before', 'value' => now()->toDateString()],
        ],
    ]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'right_to_work_expiry_date', 'operator' => 'before', 'value' => now()->toDateString()],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();
});

test('soft-deleting a candidate triggers the automation check via the deleted_at field', function () {
    $candidate = EducationCandidate::factory()->create(['first_name' => 'Jane']);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'deleted_at', 'operator' => 'filled'],
        ],
    ]);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();

    $candidate->delete();

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
});

test('an equals condition on a wildcard relation path never matches', function () {
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
        'conditions' => [
            ['field' => 'skills.*', 'operator' => 'equals', 'value' => 'anything'],
        ],
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();
});

test('ChangeCandidateStatus logs a status automation activity', function () {
    $candidate = EducationCandidate::factory()->create(['first_name' => 'Jane']);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    $automation = CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => filledConditions(['first_name']),
    ]);

    ChangeCandidateStatus::run($candidate, $automation);

    $activity = $candidate->activities()->first();

    expect($activity)->not->toBeNull();
    expect($activity->type)->toBe(ActivityType::StatusAutomation);

    $body = json_decode($activity->body, true);
    expect($body['from'])->toBe('Application Sent');
    expect($body['to'])->toBe('Onboarding');
    expect($body['conditions'])->toBe(filledConditions(['first_name']));
    expect($body['snapshot'])->toHaveKey('first_name');
});

test('observer triggers automation check when candidate is updated', function () {
    $candidate = EducationCandidate::factory()->create(['first_name' => 'Jane', 'email' => null]);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => filledConditions(['first_name', 'email']),
    ]);

    // Automation should not fire yet — email is missing
    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();

    // Filling in the missing field via a model update should trigger the automation
    $candidate->update(['email' => 'jane@example.com']);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
});

test('observer triggers automation check when a user is created for a candidate', function () {
    $candidate = EducationCandidate::factory()->create(['first_name' => 'Jane', 'email' => 'jane@example.com']);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => filledConditions(['first_name', 'email']),
    ]);

    // Automation should not fire yet — no user account exists for the candidate
    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();

    User::factory()->create([
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
});

test('observer does not trigger the automation check for a user without a candidate', function () {
    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => filledConditions(['first_name']),
    ]);

    User::factory()->create();

    // No exception, and nothing to assert against a status change since there's no candidate —
    // this just proves the observer doesn't error out when candidate_id is null.
    expect(true)->toBeTrue();
});

test('observer does not re-run the automation check on unrelated user updates', function () {
    $candidate = EducationCandidate::factory()->create(['first_name' => 'Jane', 'email' => 'jane@example.com']);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => filledConditions(['first_name', 'email']),
    ]);

    $user = User::factory()->create([
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);

    // Move the candidate back to the "from" status to prove a later, unrelated
    // user update doesn't re-trigger the automation and bounce it forward again.
    $candidate->statuses()->delete();
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    $user->update(['name' => 'Jane Updated']);

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();
});

test('CheckCandidateStatusAutomations logs activity when status changes', function () {
    $candidate = EducationCandidate::factory()->create(['first_name' => 'Jane', 'email' => 'jane@example.com']);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => filledConditions(['first_name', 'email']),
    ]);

    CheckCandidateStatusAutomations::run($candidate);

    expect($candidate->activities()->where('type', ActivityType::StatusAutomation->value)->exists())->toBeTrue();
});
