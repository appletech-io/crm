<?php

use App\Actions\References\ResendReferenceRequestEmail;
use App\Enums\ReferenceStatus;
use App\Jobs\SendReferenceRequestEmail;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\ReferenceForm;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

test('it assigns a fresh token and expiry, moves the status to contacted, and dispatches the email', function () {
    $candidate = EducationCandidate::factory()->create();

    $reference = $candidate->references()->create([
        'type' => 'professional', 'first_name' => 'Jane', 'last_name' => 'Doe',
        'email' => 'jane@example.com', 'contact_now' => true, 'status' => 'contacted', 'consent_to_contact' => true,
        'token' => 'stale-token', 'expires_on' => now()->subDay(),
    ]);

    ResendReferenceRequestEmail::run($reference);
    $reference->refresh();

    expect($reference->token)->not->toBe('stale-token');
    expect($reference->expires_on->toDateString())->toBe(now()->addDays(7)->toDateString());
    expect($reference->status)->toBe(ReferenceStatus::Contacted);
    expect($reference->last_contacted->toDateString())->toBe(now()->toDateString());

    Queue::assertPushed(SendReferenceRequestEmail::class, fn ($job) => $job->reference->is($reference));
});

test('it resends for a reference that has never been contacted before', function () {
    $candidate = EducationCandidate::factory()->create();

    $reference = $candidate->references()->create([
        'type' => 'professional', 'first_name' => 'Jane', 'last_name' => 'Doe',
        'email' => 'jane@example.com', 'contact_now' => true, 'status' => 'pending', 'consent_to_contact' => true,
    ]);

    ResendReferenceRequestEmail::run($reference);

    expect($reference->fresh()->status)->toBe(ReferenceStatus::Contacted);
    Queue::assertPushed(SendReferenceRequestEmail::class);
});

test('it does not send when contact_now is false', function () {
    $candidate = EducationCandidate::factory()->create();

    $reference = $candidate->references()->create([
        'type' => 'professional', 'first_name' => 'Jane', 'last_name' => 'Doe',
        'email' => 'jane@example.com', 'contact_now' => false, 'status' => 'pending', 'consent_to_contact' => true,
    ]);

    ResendReferenceRequestEmail::run($reference);

    expect($reference->fresh()->status)->toBe(ReferenceStatus::Pending);
    Queue::assertNotPushed(SendReferenceRequestEmail::class);
});

test('it does not send when there is no email address', function () {
    $candidate = EducationCandidate::factory()->create();

    $reference = $candidate->references()->create([
        'type' => 'professional', 'first_name' => 'Jane', 'last_name' => 'Doe',
        'email' => null, 'contact_now' => true, 'status' => 'pending', 'consent_to_contact' => true,
    ]);

    ResendReferenceRequestEmail::run($reference);

    Queue::assertNotPushed(SendReferenceRequestEmail::class);
});

test('it generates a different token each time it is resent', function () {
    $candidate = EducationCandidate::factory()->create();

    $reference = $candidate->references()->create([
        'type' => 'professional', 'first_name' => 'Jane', 'last_name' => 'Doe',
        'email' => 'jane@example.com', 'contact_now' => true, 'status' => 'contacted', 'consent_to_contact' => true,
        'token' => 'first-token',
    ]);

    ResendReferenceRequestEmail::run($reference);
    $firstResendToken = $reference->fresh()->token;

    ResendReferenceRequestEmail::run($reference);
    $secondResendToken = $reference->fresh()->token;

    expect($firstResendToken)->not->toBe('first-token');
    expect($secondResendToken)->not->toBe($firstResendToken);
});

test('resending never re-snapshots the schema a reference was originally issued with', function () {
    $candidate = EducationCandidate::factory()->create();

    $form = ReferenceForm::factory()->create([
        'company_id' => $candidate->company_id,
        'industry_id' => Industry::factory()->create()->id,
    ]);

    $reference = $candidate->references()->create([
        'reference_form_id' => $form->id, 'first_name' => 'Jane', 'last_name' => 'Doe',
        'email' => 'jane@example.com', 'contact_now' => true, 'status' => 'pending', 'consent_to_contact' => true,
    ]);

    $originalSchema = $reference->fresh()->schema;
    expect($originalSchema)->not->toBeNull();

    // Editing the form's fields after issuing the reference must never
    // change what a resend shows the referee — only the initial creation
    // snapshots the schema.
    $form->fields()->create(['label' => 'A brand new question added later', 'field_type' => 'text']);

    ResendReferenceRequestEmail::run($reference);

    expect($reference->fresh()->schema)->toBe($originalSchema);
});
