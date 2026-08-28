<?php

use App\Enums\CandidateAvailabilityStatus;
use App\Filament\EducationCandidate\Pages\Availability;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function makeAvailabilityCandidateUser(string $candidateClass = EducationCandidate::class): User
{
    $company = Company::factory()->create();
    $candidate = $candidateClass::factory()->create(['company_id' => $company->id]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => $candidateClass,
    ]);
    $user->assignRole('candidate');

    return $user;
}

test('a candidate can access their own availability page', function () {
    $user = makeAvailabilityCandidateUser();
    $this->actingAs($user);

    Livewire::test(Availability::class)->assertSuccessful();
});

test('a candidate can set their own availability', function () {
    $user = makeAvailabilityCandidateUser();
    $this->actingAs($user);

    Livewire::test(Availability::class)
        ->call('setAvailabilityStatus', '2026-08-10', CandidateAvailabilityStatus::AvailablePm->value);

    $candidate = $user->candidate;
    $availability = $candidate->availabilities()->whereDate('date', '2026-08-10')->first();

    expect($availability)->not->toBeNull();
    expect($availability->status)->toBe(CandidateAvailabilityStatus::AvailablePm);
});

test('a generic candidate can access and set their own availability too', function () {
    $user = makeAvailabilityCandidateUser(Candidate::class);
    $this->actingAs($user);

    Livewire::test(Availability::class)
        ->assertSuccessful()
        ->call('setAvailabilityStatus', '2026-08-10', CandidateAvailabilityStatus::AvailablePm->value);

    $candidate = $user->candidate;
    $availability = $candidate->availabilities()->whereDate('date', '2026-08-10')->first();

    expect($availability)->not->toBeNull();
    expect($availability->status)->toBe(CandidateAvailabilityStatus::AvailablePm);
});

test('a candidate cannot set availability for another candidate by tampering with the date only, it still writes to their own record', function () {
    $userA = makeAvailabilityCandidateUser();
    $userB = makeAvailabilityCandidateUser();

    $this->actingAs($userA);

    Livewire::test(Availability::class)
        ->call('setAvailabilityStatus', '2026-08-10', CandidateAvailabilityStatus::Available->value);

    expect($userA->candidate->availabilities()->count())->toBe(1);
    expect($userB->candidate->availabilities()->count())->toBe(0);
});

test('it works the same way for a healthcare candidate portal login', function () {
    $user = makeAvailabilityCandidateUser(HealthcareCandidate::class);
    $this->actingAs($user);

    Livewire::test(Availability::class)
        ->call('setAvailabilityStatus', '2026-08-10', CandidateAvailabilityStatus::NotAvailable->value);

    $candidate = $user->candidate;
    expect($candidate->availabilities()->whereDate('date', '2026-08-10')->first()->status)
        ->toBe(CandidateAvailabilityStatus::NotAvailable);
});

test('month navigation moves the displayed month forward and back', function () {
    $user = makeAvailabilityCandidateUser();
    $this->actingAs($user);

    $component = Livewire::test(Availability::class);

    $initialMonthStart = $component->get('monthStart');

    $component->call('goToNextMonth');
    expect($component->get('monthStart'))->toBe(Carbon::parse($initialMonthStart)->addMonthNoOverflow()->toDateString());

    $component->call('goToPreviousMonth');
    expect($component->get('monthStart'))->toBe($initialMonthStart);
});

test('a candidate can select multiple days and bulk apply a status', function () {
    $user = makeAvailabilityCandidateUser();
    $this->actingAs($user);

    Livewire::test(Availability::class)
        ->call('toggleDaySelection', '2026-08-10')
        ->call('toggleDaySelection', '2026-08-11')
        ->assertSet('selectedDates', ['2026-08-10', '2026-08-11'])
        ->call('applyAvailabilityStatus', CandidateAvailabilityStatus::Available->value)
        ->assertSet('selectedDates', []);

    $candidate = $user->candidate;
    expect($candidate->availabilities()->whereDate('date', '2026-08-10')->first()->status)->toBe(CandidateAvailabilityStatus::Available);
    expect($candidate->availabilities()->whereDate('date', '2026-08-11')->first()->status)->toBe(CandidateAvailabilityStatus::Available);
});
