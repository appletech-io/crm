<?php

use App\Actions\Candidates\CandidateCreated;
use App\Jobs\SendApplicationEmail;
use App\Models\CandidateStatus;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($this->industry);

    CandidateStatus::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Onboarding',
    ]);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->actingAs($this->user);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('it dispatches SendApplicationEmail with the id of the user who created the candidate', function () {
    Queue::fake();

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    CandidateCreated::run($candidate);

    Queue::assertPushed(SendApplicationEmail::class, fn (SendApplicationEmail $job): bool => $job->candidate->is($candidate)
        && $job->createdByUserId === $this->user->id);
});

test('it creates a pending application and assigns the onboarding status', function () {
    Queue::fake();

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    CandidateCreated::run($candidate);

    expect($candidate->application)->not->toBeNull()
        ->and($candidate->application->status)->toBe('pending')
        ->and($candidate->currentStatusName())->toBe('Onboarding');
});
