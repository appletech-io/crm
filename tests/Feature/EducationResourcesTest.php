<?php

use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use App\Models\EducationCandidate;
use App\Models\EducationBooking;
use App\Models\EducationClient;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Filament\Resources\EducationBookings\EducationBookingResource;
use Illuminate\Support\Facades\Auth;

test('education candidate and booking resources have correct visibility', function () {
    $educationIndustry = Industry::factory()->create(['slug' => 'education']);
    $company = Company::factory()->create();
    $company->industries()->attach($educationIndustry);

    $user = User::factory()->create(['company_id' => $company->id]);
    Auth::login($user);

    // Should be hidden because user doesn't have the industry
    expect(EducationCandidateResource::canViewAny())->toBeFalse()
        ->and(EducationBookingResource::canViewAny())->toBeFalse();

    $user->industries()->attach($educationIndustry);

    // Should be visible now
    expect(EducationCandidateResource::canViewAny())->toBeTrue()
        ->and(EducationBookingResource::canViewAny())->toBeTrue();
});

test('education candidate and booking are scoped to company', function () {
    $company1 = Company::factory()->create();
    $company2 = Company::factory()->create();

    $user = User::factory()->create(['company_id' => $company1->id]);
    Auth::login($user);

    $candidate1 = EducationCandidate::factory()->create(['company_id' => $company1->id]);
    $candidate2 = EducationCandidate::factory()->create(['company_id' => $company2->id]);

    expect(EducationCandidate::all())->toHaveCount(1)
        ->and(EducationCandidate::first()->id)->toBe($candidate1->id);

    $client1 = EducationClient::factory()->create(['company_id' => $company1->id]);
    $booking1 = EducationBooking::factory()->create([
        'company_id' => $company1->id,
        'education_client_id' => $client1->id,
        'education_candidate_id' => $candidate1->id,
    ]);

    $booking2 = EducationBooking::factory()->create(['company_id' => $company2->id]);

    expect(EducationBooking::all())->toHaveCount(1)
        ->and(EducationBooking::first()->id)->toBe($booking1->id);
});
