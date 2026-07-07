<?php

use App\Models\EducationApplication;
use App\Models\EducationCandidate;
use App\Services\ApplicationAccessSession;
use Livewire\Livewire;

function makeUnverifiedApplication(array $attributes = []): EducationApplication
{
    $candidate = EducationCandidate::factory()->create();

    return EducationApplication::factory()->create(array_merge([
        'education_candidate_id' => $candidate->id,
        'status' => 'pending',
        'email' => 'jane@example.com',
        'email_verified' => false,
    ], $attributes));
}

test('mount aborts 404 for unknown token', function () {
    Livewire::test('application.verify-application', ['token' => 'invalid-token'])
        ->assertStatus(404);
});

test('mount aborts 403 for an expired application', function () {
    $application = makeUnverifiedApplication(['expires_on' => now()->subDay(), 'status' => 'expired']);

    Livewire::test('application.verify-application', ['token' => $application->token])
        ->assertStatus(403);
});

test('mount redirects to login and flashes a toast for a completed application', function () {
    $application = makeUnverifiedApplication(['status' => 'completed']);

    Livewire::test('application.verify-application', ['token' => $application->token])
        ->assertRedirect(route('login'));

    expect(session('toast'))->toBe(['text' => 'Application Completed', 'variant' => 'success']);
});

test('mount shows the verify form for a session that has not verified this application', function () {
    $application = makeUnverifiedApplication();

    Livewire::test('application.verify-application', ['token' => $application->token])
        ->assertSuccessful()
        ->assertSee('Verify Your Identity');
});

test('the page renders over a real HTTP request as a guest without an authenticated user', function () {
    $application = makeUnverifiedApplication();

    $this->get(route('application.verify', ['token' => $application->token]))
        ->assertSuccessful()
        ->assertSee('Verify Your Identity');
});

test('mount redirects straight to the form once this session has already verified the application', function () {
    $application = makeUnverifiedApplication();

    ApplicationAccessSession::markVerified($application->token);

    Livewire::test('application.verify-application', ['token' => $application->token])
        ->assertRedirect(route('application.form', ['token' => $application->token]));
});

test('verify rejects an email that does not match the application', function () {
    $application = makeUnverifiedApplication();

    Livewire::test('application.verify-application', ['token' => $application->token])
        ->set('email', 'someone-else@example.com')
        ->call('verify')
        ->assertHasErrors(['email']);

    expect($application->fresh()->email_verified)->toBeFalse();
    expect(ApplicationAccessSession::hasVerified($application->token))->toBeFalse();
});

test('verify accepts a case-insensitive match and grants access to the form for this session', function () {
    $application = makeUnverifiedApplication();

    Livewire::test('application.verify-application', ['token' => $application->token])
        ->set('email', 'JANE@EXAMPLE.COM')
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('application.form', ['token' => $application->token]));

    expect($application->fresh()->email_verified)->toBeTrue();
    expect(ApplicationAccessSession::hasVerified($application->token))->toBeTrue();
});
