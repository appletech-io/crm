<?php

use App\Actions\References\SendReferenceRequestEmails;
use App\Enums\ReferenceStatus;
use App\Jobs\SendReferenceRequestEmail;
use App\Models\EducationCandidate;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

test('it emails only references with contact_now, an email address, and pending status', function () {
    $candidate = EducationCandidate::factory()->create();

    $eligible = $candidate->references()->create([
        'type' => 'professional', 'first_name' => 'Jane', 'last_name' => 'Doe',
        'email' => 'jane@example.com', 'contact_now' => true, 'status' => 'pending', 'consent_to_contact' => true,
    ]);

    $notContactableYet = $candidate->references()->create([
        'type' => 'professional', 'first_name' => 'No', 'last_name' => 'Contact',
        'email' => 'no@example.com', 'contact_now' => false, 'status' => 'pending', 'consent_to_contact' => true,
    ]);

    $noEmail = $candidate->references()->create([
        'type' => 'professional', 'first_name' => 'No', 'last_name' => 'Email',
        'email' => null, 'contact_now' => true, 'status' => 'pending', 'consent_to_contact' => true,
    ]);

    $alreadyContacted = $candidate->references()->create([
        'type' => 'professional', 'first_name' => 'Already', 'last_name' => 'Contacted',
        'email' => 'already@example.com', 'contact_now' => true, 'status' => 'contacted', 'consent_to_contact' => true,
    ]);

    SendReferenceRequestEmails::run($candidate);

    Queue::assertPushed(SendReferenceRequestEmail::class, fn ($job) => $job->reference->is($eligible));
    Queue::assertNotPushed(SendReferenceRequestEmail::class, fn ($job) => $job->reference->is($notContactableYet));
    Queue::assertNotPushed(SendReferenceRequestEmail::class, fn ($job) => $job->reference->is($noEmail));
    Queue::assertNotPushed(SendReferenceRequestEmail::class, fn ($job) => $job->reference->is($alreadyContacted));
});

test('it assigns a fresh token, an expiry date, and moves the status to contacted', function () {
    $candidate = EducationCandidate::factory()->create();

    $reference = $candidate->references()->create([
        'type' => 'professional', 'first_name' => 'Jane', 'last_name' => 'Doe',
        'email' => 'jane@example.com', 'contact_now' => true, 'status' => 'pending', 'consent_to_contact' => true,
    ]);

    SendReferenceRequestEmails::run($candidate);

    $reference->refresh();

    expect($reference->token)->not->toBeNull();
    expect($reference->expires_on->toDateString())->toBe(now()->addDays(7)->toDateString());
    expect($reference->status)->toBe(ReferenceStatus::Contacted);
    expect($reference->last_contacted->toDateString())->toBe(now()->toDateString());
});

test('it generates a different token for each reference', function () {
    $candidate = EducationCandidate::factory()->create();

    $referenceA = $candidate->references()->create([
        'type' => 'professional', 'first_name' => 'A', 'last_name' => 'One',
        'email' => 'a@example.com', 'contact_now' => true, 'status' => 'pending', 'consent_to_contact' => true,
    ]);
    $referenceB = $candidate->references()->create([
        'type' => 'academic', 'first_name' => 'B', 'last_name' => 'Two',
        'email' => 'b@example.com', 'contact_now' => true, 'status' => 'pending', 'consent_to_contact' => true,
    ]);

    SendReferenceRequestEmails::run($candidate);

    expect($referenceA->fresh()->token)->not->toBe($referenceB->fresh()->token);
});
