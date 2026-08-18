<?php

use App\Actions\Applications\ResendApplicationEmail;
use App\Jobs\SendApplicationEmail;
use App\Models\EducationApplication;
use App\Models\EducationCandidate;
use App\Models\HealthcareApplication;
use App\Models\HealthcareCandidate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Queue::fake();
});

test('it assigns a fresh token and a 2 week expiry, then dispatches the application email', function () {
    $candidate = EducationCandidate::factory()->create();

    $application = EducationApplication::factory()->create([
        'education_candidate_id' => $candidate->id,
        'token' => 'stale-token',
        'expires_on' => now()->subDay(),
    ]);

    ResendApplicationEmail::run($candidate);
    $application->refresh();

    expect($application->token)->not->toBe('stale-token');
    expect($application->expires_on->toDateString())->toBe(now()->addWeeks(2)->toDateString());

    Queue::assertPushed(SendApplicationEmail::class, fn (SendApplicationEmail $job): bool => $job->application->is($application)
        && $job->candidate->is($candidate));
});

test('it works for a healthcare candidate too', function () {
    $candidate = HealthcareCandidate::factory()->create();

    $application = HealthcareApplication::create([
        'candidate_type' => HealthcareCandidate::class,
        'candidate_id' => $candidate->id,
        'email' => $candidate->email,
        'status' => 'pending',
        'token' => 'stale-token',
        'expires_on' => now()->subDay(),
    ]);

    ResendApplicationEmail::run($candidate);
    $application->refresh();

    expect($application->token)->not->toBe('stale-token');
    expect($application->expires_on->toDateString())->toBe(now()->addWeeks(2)->toDateString());

    Queue::assertPushed(SendApplicationEmail::class, fn (SendApplicationEmail $job): bool => $job->application->is($application));
});

test('it does nothing when the candidate has no application', function () {
    $candidate = EducationCandidate::factory()->create();

    ResendApplicationEmail::run($candidate);

    Queue::assertNotPushed(SendApplicationEmail::class);
});

test('it generates a different token each time it is resent', function () {
    $candidate = EducationCandidate::factory()->create();

    $application = EducationApplication::factory()->create([
        'education_candidate_id' => $candidate->id,
        'token' => Str::random(32),
    ]);

    ResendApplicationEmail::run($candidate);
    $firstResendToken = $application->fresh()->token;

    ResendApplicationEmail::run($candidate);
    $secondResendToken = $application->fresh()->token;

    expect($firstResendToken)->not->toBe($secondResendToken);
});
