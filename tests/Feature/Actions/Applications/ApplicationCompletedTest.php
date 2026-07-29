<?php

use App\Actions\Applications\ApplicationCompleted;
use App\Jobs\SendReferenceRequestEmail;
use App\Models\EducationApplication;
use Illuminate\Support\Facades\Queue;

test('it can be run with a completed application', function () {
    $application = EducationApplication::factory()->create(['status' => 'completed']);

    ApplicationCompleted::run($application);
})->throwsNoExceptions();

test('it dispatches reference request emails for the candidates contactable references', function () {
    Queue::fake();

    $application = EducationApplication::factory()->create(['status' => 'completed']);
    $candidate = $application->educationCandidate;

    $reference = $candidate->references()->create([
        'type' => 'professional', 'first_name' => 'Ref', 'last_name' => 'Eree',
        'email' => 'referee@example.com', 'consent_to_contact' => true, 'contact_now' => true, 'status' => 'pending',
    ]);

    ApplicationCompleted::run($application);

    Queue::assertPushed(SendReferenceRequestEmail::class, fn ($job) => $job->reference->is($reference));
});
