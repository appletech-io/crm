<?php

use App\Models\CandidateStatus;
use App\Models\CandidateStatusAutomation;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(function () {
    $this->industry = Industry::factory()->create();

    $this->fromStatus = CandidateStatus::factory()->create([
        'industry_id' => $this->industry->id,
        'name' => 'Onboarding',
    ]);

    $this->toStatus = CandidateStatus::factory()->create([
        'industry_id' => $this->industry->id,
        'name' => 'Vetting',
    ]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'available_from', 'operator' => 'days_since_at_least', 'value' => '30'],
        ],
    ]);
});

test('moves an education candidate whose time-based condition has now elapsed', function () {
    $candidate = EducationCandidate::factory()->create(['available_from' => now()->subDays(40)->toDateString()]);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    $this->artisan('automations:check-time-based')->assertSuccessful();

    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeTrue();
});

test('does not move a candidate whose time-based condition has not yet elapsed', function () {
    $candidate = EducationCandidate::factory()->create(['available_from' => now()->subDays(10)->toDateString()]);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    $this->artisan('automations:check-time-based')->assertSuccessful();

    expect($candidate->statuses()->where('candidate_status_id', $this->fromStatus->id)->exists())->toBeTrue();
    expect($candidate->statuses()->where('candidate_status_id', $this->toStatus->id)->exists())->toBeFalse();
});

test('sweeps candidates across both education and healthcare models', function () {
    $healthcareFromStatus = CandidateStatus::factory()->create([
        'industry_id' => $this->industry->id,
        'name' => 'Healthcare Onboarding',
    ]);

    $healthcareToStatus = CandidateStatus::factory()->create([
        'industry_id' => $this->industry->id,
        'name' => 'Healthcare Vetting',
    ]);

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $healthcareFromStatus->id,
        'to_candidate_status_id' => $healthcareToStatus->id,
        'conditions' => [
            ['field' => 'available_from', 'operator' => 'days_since_at_least', 'value' => '30'],
        ],
    ]);

    $healthcareCandidate = HealthcareCandidate::factory()->create(['available_from' => now()->subDays(40)->toDateString()]);
    $healthcareCandidate->statuses()->create(['candidate_status_id' => $healthcareFromStatus->id]);

    $this->artisan('automations:check-time-based')->assertSuccessful();

    expect($healthcareCandidate->statuses()->where('candidate_status_id', $healthcareToStatus->id)->exists())->toBeTrue();
});

test('does nothing when no automation has a time-based condition', function () {
    CandidateStatusAutomation::query()->delete();

    CandidateStatusAutomation::factory()->create([
        'candidate_status_id' => $this->fromStatus->id,
        'to_candidate_status_id' => $this->toStatus->id,
        'conditions' => [
            ['field' => 'first_name', 'operator' => 'filled'],
        ],
    ]);

    $candidate = EducationCandidate::factory()->create(['first_name' => 'Jane']);
    $candidate->statuses()->create(['candidate_status_id' => $this->fromStatus->id]);

    $this->artisan('automations:check-time-based')->assertSuccessful();

    expect($candidate->statuses()->where('candidate_status_id', $this->fromStatus->id)->exists())->toBeTrue();
});

test('the command is registered on the daily schedule', function () {
    $events = app(Schedule::class)->events();

    $matching = collect($events)->first(fn ($event) => str_contains($event->command, 'automations:check-time-based'));

    expect($matching)->not->toBeNull();
    expect($matching->expression)->toBe('0 0 * * *');
});
