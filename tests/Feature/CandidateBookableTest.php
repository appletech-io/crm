<?php

use App\Actions\Candidates\RecalculateCandidateRating;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Support\CandidateSummaryAction;
use App\Models\Booking;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use App\Services\Booking\BookingEligibility;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

/**
 * Covers the generic Candidate model being fully bookable — the same
 * booking lifecycle (create/save/delete triggers rating recalculation,
 * eligibility checks don't crash, "Quick view" renders) that already works
 * for EducationCandidate/HealthcareCandidate.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'generic']);
    $this->company->industries()->attach($this->industry);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->industry);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('saving a booking for a generic candidate recalculates their rating instead of crashing', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Booking::factory()->create([
        'company_id' => $this->company->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => Candidate::class,
        'candidate_rating' => 4,
    ]);

    Booking::factory()->create([
        'company_id' => $this->company->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => Candidate::class,
        'candidate_rating' => 2,
    ]);

    expect($candidate->refresh()->average_rating)->toBe(3.0)
        ->and($candidate->ratings_count)->toBe(2);
});

test('recalculating a generic candidate rating with no rated bookings clears it', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'average_rating' => 5,
        'ratings_count' => 1,
    ]);

    RecalculateCandidateRating::run($candidate);

    expect($candidate->refresh()->average_rating)->toBeNull()
        ->and($candidate->ratings_count)->toBe(0);
});

test('booking eligibility raises no restriction for a generic candidate, which has no qualification concept', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    expect(BookingEligibility::disallowedJobTitleReason($candidate, 1))->toBeNull();
});

test('the quick view action mounts without error for a generic candidate booking row', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => Candidate::class,
    ]);

    Livewire::test(ListBookings::class)
        ->mountTableAction('viewCandidateSummary', $booking)
        ->assertSuccessful();
});

test('overview data reflects a generic candidate\'s own details', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'email' => 'jane@example.com',
        'phone' => '01234567890',
        'average_rating' => 4.5,
        'ratings_count' => 3,
    ]);

    $data = CandidateSummaryAction::overviewData($candidate);

    expect($data['email'])->toBe('jane@example.com')
        ->and($data['phone'])->toBe('01234567890')
        ->and($data['rating'])->toContain('4.5');
});
