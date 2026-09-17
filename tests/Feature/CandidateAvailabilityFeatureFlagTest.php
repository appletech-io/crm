<?php

use App\Filament\EducationCandidate\Pages\Availability;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('the Availability page is hidden for an EducationCandidate whose company/industry has bookings off', function () {
    $company = Company::factory()->create();
    $industry = Industry::factory()->create(['slug' => 'education']);
    $company->industries()->attach($industry->id, ['uses_bookings' => false]);

    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);
    $user = User::factory()->create([
        'company_id' => $company->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);
    $user->assignRole('candidate');
    $this->actingAs($user);

    expect(Availability::canAccess())->toBeFalse();
});

test('the Availability page stays visible for an EducationCandidate whose company/industry has bookings on', function () {
    $company = Company::factory()->create();
    $industry = Industry::factory()->create(['slug' => 'education']);
    $company->industries()->attach($industry->id, ['uses_bookings' => true]);

    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);
    $user = User::factory()->create([
        'company_id' => $company->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);
    $user->assignRole('candidate');
    $this->actingAs($user);

    expect(Availability::canAccess())->toBeTrue();
});

test('the Availability page is hidden for a generic Candidate whose company/industry has bookings off', function () {
    $company = Company::factory()->create();
    $industry = Industry::factory()->create(['slug' => 'generic']);
    $company->industries()->attach($industry->id, ['uses_bookings' => false]);

    $candidate = Candidate::factory()->create(['company_id' => $company->id, 'industry_id' => $industry->id]);
    $user = User::factory()->create([
        'company_id' => $company->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => Candidate::class,
    ]);
    $user->assignRole('candidate');
    $this->actingAs($user);

    expect(Availability::canAccess())->toBeFalse();
});

test('the Availability page is hidden with no user logged in', function () {
    expect(Availability::canAccess())->toBeFalse();
});
