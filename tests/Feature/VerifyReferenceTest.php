<?php

use App\Enums\ReferenceStatus;
use App\Models\CandidateReference;
use App\Models\EducationCandidate;
use App\Models\User;
use App\Services\ReferenceAccessSession;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

function makeContactedReference(array $attributes = []): CandidateReference
{
    $candidate = EducationCandidate::factory()->create();

    return $candidate->references()->create(array_merge([
        'type' => 'professional',
        'first_name' => 'Ref',
        'last_name' => 'Eree',
        'email' => 'referee@example.com',
        'consent_to_contact' => true,
        'contact_now' => true,
        'status' => ReferenceStatus::Contacted,
        'token' => 'the-token-'.uniqid(),
        'expires_on' => now()->addDays(7),
    ], $attributes));
}

test('mount aborts 404 for an unknown token', function () {
    Livewire::test('reference.verify-reference', ['token' => 'invalid-token'])
        ->assertStatus(404);
});

test('mount aborts 403 for an expired reference', function () {
    $reference = makeContactedReference(['expires_on' => now()->subDay()]);

    Livewire::test('reference.verify-reference', ['token' => $reference->token])
        ->assertStatus(403);
});

test('mount does not abort for an expired reference that has already been submitted', function () {
    $reference = makeContactedReference(['expires_on' => now()->subDay(), 'status' => ReferenceStatus::Submitted]);

    Livewire::test('reference.verify-reference', ['token' => $reference->token])
        ->assertSuccessful();
});

test('mount shows the verify form for a session that has not verified this reference', function () {
    $reference = makeContactedReference();

    Livewire::test('reference.verify-reference', ['token' => $reference->token])
        ->assertSuccessful()
        ->assertSee('Verify Your Identity');
});

test('the page renders over a real HTTP request as a guest without an authenticated user', function () {
    $reference = makeContactedReference();

    $this->get(route('reference.verify', ['token' => $reference->token]))
        ->assertSuccessful()
        ->assertSee('Verify Your Identity');
});

test('mount redirects straight to the form once this session has already verified the reference', function () {
    $reference = makeContactedReference();

    ReferenceAccessSession::markVerified($reference->token);

    Livewire::test('reference.verify-reference', ['token' => $reference->token])
        ->assertRedirect(route('reference.form', ['token' => $reference->token]));
});

test('verify rejects an email that does not match the reference', function () {
    $reference = makeContactedReference();

    Livewire::test('reference.verify-reference', ['token' => $reference->token])
        ->set('email', 'someone-else@example.com')
        ->call('verify')
        ->assertHasErrors(['email']);

    expect(ReferenceAccessSession::hasVerified($reference->token))->toBeFalse();
});

test('verify rejects when the reference has no email on file', function () {
    $reference = makeContactedReference(['email' => null]);

    Livewire::test('reference.verify-reference', ['token' => $reference->token])
        ->set('email', 'anything@example.com')
        ->call('verify')
        ->assertHasErrors(['email']);
});

test('verify accepts a case-insensitive match and grants access to the form for this session', function () {
    $reference = makeContactedReference();

    Livewire::test('reference.verify-reference', ['token' => $reference->token])
        ->set('email', 'REFEREE@EXAMPLE.COM')
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('reference.form', ['token' => $reference->token]));

    expect(ReferenceAccessSession::hasVerified($reference->token))->toBeTrue();
});

test('a logged-in CRM user skips the email challenge and is sent straight to the form', function () {
    $this->seed(RoleSeeder::class);
    $reference = makeContactedReference();

    $consultant = User::factory()->create();
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    Livewire::test('reference.verify-reference', ['token' => $reference->token])
        ->assertRedirect(route('reference.form', ['token' => $reference->token]));

    expect(ReferenceAccessSession::hasVerified($reference->token))->toBeTrue();
});

test('a logged-in CRM user is not blocked by an expired, unsubmitted reference', function () {
    $this->seed(RoleSeeder::class);
    $reference = makeContactedReference(['expires_on' => now()->subDay()]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test('reference.verify-reference', ['token' => $reference->token])
        ->assertRedirect(route('reference.form', ['token' => $reference->token]));
});

test('a logged-in candidate user still has to verify by email, since they are not a CRM user', function () {
    $this->seed(RoleSeeder::class);
    $reference = makeContactedReference();

    $candidateUser = User::factory()->create();
    $candidateUser->assignRole('candidate');
    $this->actingAs($candidateUser);

    Livewire::test('reference.verify-reference', ['token' => $reference->token])
        ->assertSuccessful()
        ->assertSee('Verify Your Identity');

    expect(ReferenceAccessSession::hasVerified($reference->token))->toBeFalse();
});
