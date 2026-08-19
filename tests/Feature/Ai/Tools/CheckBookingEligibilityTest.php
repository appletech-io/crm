<?php

use App\Ai\Tools\CheckBookingEligibility;
use App\Enums\CandidateAvailabilityStatus;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Models\CandidateAvailability;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\Qualification;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('it returns a message when the candidate is not found', function () {
    $result = (new CheckBookingEligibility)->handle(new Request(['candidate_name' => 'Nobody', 'job_title' => 'Teacher']));

    expect($result)->toBe('No candidate matching "Nobody" was found.');
});

test('it asks for a job title or date range when neither is provided', function () {
    EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $result = (new CheckBookingEligibility)->handle(new Request(['candidate_name' => 'Jane']));

    expect($result)->toBe('Tell me a job title, a date range, or both, to check eligibility for.');
});

test('it returns a message when the job title is not found', function () {
    EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $result = (new CheckBookingEligibility)->handle(new Request(['candidate_name' => 'Jane', 'job_title' => 'Nonexistent Role']));

    expect($result)->toBe('No job title matching "Nonexistent Role" was found.');
});

test('it confirms eligibility when the qualification allows the job title', function () {
    $jobTitle = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Teacher',
    ]);
    $qualification = Qualification::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);
    $qualification->jobTitles()->attach($jobTitle->id, [
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'qualification_id' => $qualification->id,
    ]);

    $result = (new CheckBookingEligibility)->handle(new Request(['candidate_name' => 'Jane', 'job_title' => 'Teacher']));

    $url = EducationCandidateResource::getUrl('edit', ['record' => $candidate]);
    expect($result)->toBe("Yes — [Jane Doe]({$url}) can be booked as Teacher.");
});

test('it explains when the qualification does not allow the job title', function () {
    $allowedTitle = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Teaching Assistant',
    ]);
    $disallowedTitle = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Teacher',
    ]);
    $qualification = Qualification::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'TA Qualification',
    ]);
    $qualification->jobTitles()->attach($allowedTitle->id, [
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'qualification_id' => $qualification->id,
    ]);

    $result = (new CheckBookingEligibility)->handle(new Request(['candidate_name' => 'Jane', 'job_title' => 'Teacher']));

    $url = EducationCandidateResource::getUrl('edit', ['record' => $candidate]);
    expect($result)->toBe(
        "[Jane Doe]({$url}) cannot be booked: This candidate's qualification (TA Qualification) does not allow working as Teacher."
    );
});

test('it explains when the candidate is not available on the requested dates', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    CandidateAvailability::create([
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'date' => '2026-09-07',
        'status' => CandidateAvailabilityStatus::NotAvailable,
    ]);

    $result = (new CheckBookingEligibility)->handle(new Request([
        'candidate_name' => 'Jane',
        'from' => '2026-09-07',
    ]));

    $url = EducationCandidateResource::getUrl('edit', ['record' => $candidate]);
    expect($result)->toBe("[Jane Doe]({$url}) cannot be booked: This candidate is not available on: 7th Sep 2026.");
});

test('it resolves a candidate by their full first and last name together', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $result = (new CheckBookingEligibility)->handle(new Request([
        'candidate_name' => 'Jane Doe',
        'from' => '2026-09-07',
    ]));

    $url = EducationCandidateResource::getUrl('edit', ['record' => $candidate]);
    expect($result)->toBe("Yes — [Jane Doe]({$url}) can be booked.");
});

test('it confirms availability when there is no conflicting record', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $result = (new CheckBookingEligibility)->handle(new Request([
        'candidate_name' => 'Jane',
        'from' => '2026-09-07',
    ]));

    $url = EducationCandidateResource::getUrl('edit', ['record' => $candidate]);
    expect($result)->toBe("Yes — [Jane Doe]({$url}) can be booked.");
});
