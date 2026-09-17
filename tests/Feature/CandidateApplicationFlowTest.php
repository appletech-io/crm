<?php

use App\Actions\Candidates\GenericCandidateCreated;
use App\Jobs\SendApplicationEmail;
use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\CandidateStatus;
use App\Models\Company;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
use App\Services\ApplicationAccessSession;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'generic']);
    $this->company->industries()->attach($this->industry);

    CandidateStatus::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Onboarding',
    ]);

    $this->jobTitle = JobTitle::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->actingAs($this->user);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

function makePendingCandidateApplication(Candidate $candidate): CandidateApplication
{
    $application = CandidateApplication::create([
        'company_id' => $candidate->company_id,
        'candidate_id' => $candidate->id,
        'job_title_id' => $candidate->job_title_id,
        'status' => 'pending',
        'token' => (string) Str::uuid(),
        'expires_on' => now()->addWeeks(2)->toDateString(),
    ]);

    ApplicationAccessSession::markVerified($application->token);

    return $application;
}

test('GenericCandidateCreated does nothing for a candidate with no job title', function () {
    Queue::fake();

    $candidate = Candidate::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    GenericCandidateCreated::run($candidate);

    expect($candidate->application()->exists())->toBeFalse();
    Queue::assertNotPushed(SendApplicationEmail::class);
});

test('GenericCandidateCreated creates a pending application, the onboarding status, and dispatches the email', function () {
    Queue::fake();

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    GenericCandidateCreated::run($candidate);

    expect($candidate->application)->not->toBeNull()
        ->and($candidate->application->status)->toBe('pending')
        ->and($candidate->application->job_title_id)->toBe($this->jobTitle->id)
        ->and($candidate->application->expires_on->toDateString())->toBe(now()->addWeeks(2)->toDateString())
        ->and($candidate->currentStatusName())->toBe('Onboarding');

    Queue::assertPushed(SendApplicationEmail::class, fn (SendApplicationEmail $job): bool => $job->candidate->is($candidate)
        && $job->createdByUserId === $this->user->id);
});

test('verifying with the wrong email is rejected', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
        'email' => 'robin.shaw@example.com',
    ]);
    $application = CandidateApplication::create([
        'company_id' => $candidate->company_id,
        'candidate_id' => $candidate->id,
        'job_title_id' => $candidate->job_title_id,
        'status' => 'pending',
        'token' => (string) Str::uuid(),
        'expires_on' => now()->addWeeks(2)->toDateString(),
    ]);

    Livewire::test('application.candidate-verify-application', ['token' => $application->token])
        ->set('email', 'not-them@example.com')
        ->call('verify')
        ->assertHasErrors(['email']);
});

test('verifying with the correct email marks the session verified and redirects to the form', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
        'email' => 'robin.shaw@example.com',
    ]);
    $application = CandidateApplication::create([
        'company_id' => $candidate->company_id,
        'candidate_id' => $candidate->id,
        'job_title_id' => $candidate->job_title_id,
        'status' => 'pending',
        'token' => (string) Str::uuid(),
        'expires_on' => now()->addWeeks(2)->toDateString(),
    ]);

    Livewire::test('application.candidate-verify-application', ['token' => $application->token])
        ->set('email', 'ROBIN.SHAW@example.com')
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('application.candidate.form', ['token' => $application->token]));

    expect($application->fresh()->email_verified)->toBeTrue();
});

test('the form redirects to verify when the session has not been verified yet', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $application = CandidateApplication::create([
        'company_id' => $candidate->company_id,
        'candidate_id' => $candidate->id,
        'job_title_id' => $candidate->job_title_id,
        'status' => 'pending',
        'token' => (string) Str::uuid(),
        'expires_on' => now()->addWeeks(2)->toDateString(),
    ]);

    Livewire::test('application.candidate-application-form', ['token' => $application->token])
        ->assertRedirect(route('application.candidate.verify', ['token' => $application->token]));
});

test('the form lists the job title\'s document requirements before the candidate signs in', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $application = makePendingCandidateApplication($candidate);

    Livewire::test('application.candidate-application-form', ['token' => $application->token])
        ->assertSuccessful()
        ->assertSee('CV')
        ->assertSee('Proof of Address');
});

test('completeApplication requires a password with a matching confirmation', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $application = makePendingCandidateApplication($candidate);

    Livewire::test('application.candidate-application-form', ['token' => $application->token])
        ->set('password', 'super-secret-password')
        ->set('password_confirmation', 'does-not-match')
        ->call('completeApplication')
        ->assertHasErrors(['password']);

    expect($application->fresh()->status)->toBe('pending');
});

test('completeApplication creates a portal user linked to the candidate and logs them in', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
        'first_name' => 'Robin',
        'last_name' => 'Shaw',
        'email' => 'robin.shaw@example.com',
    ]);
    $application = makePendingCandidateApplication($candidate);

    Livewire::test('application.candidate-application-form', ['token' => $application->token])
        ->set('password', 'super-secret-password')
        ->set('password_confirmation', 'super-secret-password')
        ->call('completeApplication')
        ->assertHasNoErrors()
        ->assertRedirect('/candidate');

    $user = User::where('email', 'robin.shaw@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Robin Shaw')
        ->and($user->candidate_id)->toBe($candidate->id)
        ->and($user->candidate_type)->toBe(Candidate::class)
        ->and($user->hasRole('candidate'))->toBeTrue()
        ->and($user->industries()->where('industries.id', $this->industry->id)->exists())->toBeTrue()
        ->and(Hash::check('super-secret-password', $user->password))->toBeTrue();

    expect($application->fresh()->status)->toBe('completed');
    expect($application->fresh()->completed_at)->not->toBeNull();

    $this->assertAuthenticatedAs($user);
});
