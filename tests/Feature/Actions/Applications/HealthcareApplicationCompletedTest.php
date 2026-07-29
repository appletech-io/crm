<?php

use App\Actions\Applications\HealthcareApplicationCompleted;
use App\Jobs\SendReferenceRequestEmail;
use App\Models\HealthcareApplication;
use App\Models\HealthcareCandidate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function makeCompletedHealthcareApplication(): HealthcareApplication
{
    $candidate = HealthcareCandidate::factory()->create();

    return HealthcareApplication::create([
        'candidate_type' => HealthcareCandidate::class,
        'candidate_id' => $candidate->id,
        'email' => $candidate->email,
        'status' => 'completed',
        'token' => Str::random(32),
        'expires_on' => now()->addDays(7),
    ]);
}

test('it can be run with a completed application', function () {
    $application = makeCompletedHealthcareApplication();

    HealthcareApplicationCompleted::run($application);
})->throwsNoExceptions();

test('it dispatches reference request emails for the candidates contactable references', function () {
    Queue::fake();

    $application = makeCompletedHealthcareApplication();
    $candidate = $application->candidate;

    $reference = $candidate->references()->create([
        'type' => 'agency', 'first_name' => 'Ref', 'last_name' => 'Eree',
        'email' => 'referee@example.com', 'consent_to_contact' => true, 'contact_now' => true, 'status' => 'pending',
    ]);

    HealthcareApplicationCompleted::run($application);

    Queue::assertPushed(SendReferenceRequestEmail::class, fn ($job) => $job->reference->is($reference));
});
