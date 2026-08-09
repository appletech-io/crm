<?php

use App\Enums\CandidateAvailabilityStatus;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Services\Candidates\CandidateWeeklyAvailability;
use Illuminate\Support\Carbon;

test('it returns one row per day for the week containing the given date, monday to sunday', function () {
    $company = Company::factory()->create();
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);

    // A Wednesday — the week should still start on the Monday before it.
    $rows = CandidateWeeklyAvailability::forWeek($candidate, Carbon::parse('2026-08-12'));

    expect($rows)->toHaveCount(7);
    expect($rows[0]['date'])->toBe('2026-08-10');
    expect($rows[6]['date'])->toBe('2026-08-16');
});

test('a day with no stored availability has a null status and is editable', function () {
    $company = Company::factory()->create();
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);

    $rows = CandidateWeeklyAvailability::forWeek($candidate, Carbon::parse('2026-08-10'));

    expect($rows[0]['status'])->toBeNull();
    expect($rows[0]['editable'])->toBeTrue();
});

test('a day with a stored availability status reflects it', function () {
    $company = Company::factory()->create();
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);
    $candidate->availabilities()->create([
        'date' => '2026-08-11',
        'status' => CandidateAvailabilityStatus::AvailableAm->value,
    ]);

    $rows = CandidateWeeklyAvailability::forWeek($candidate, Carbon::parse('2026-08-10'));

    $tuesday = collect($rows)->firstWhere('date', '2026-08-11');
    expect($tuesday['status'])->toBe(CandidateAvailabilityStatus::AvailableAm->value);
    expect($tuesday['editable'])->toBeTrue();
});

test('a stored availability on the last day of the week (sunday) is still picked up', function () {
    // Regression test: the "date" cast reads back as date-only but writes a
    // full datetime string, so a naive whereBetween on the raw column would
    // silently exclude the week's final day.
    $company = Company::factory()->create();
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);
    $candidate->availabilities()->create([
        'date' => '2026-08-16',
        'status' => CandidateAvailabilityStatus::Available->value,
    ]);

    $rows = CandidateWeeklyAvailability::forWeek($candidate, Carbon::parse('2026-08-10'));

    $sunday = collect($rows)->firstWhere('date', '2026-08-16');
    expect($sunday['status'])->toBe(CandidateAvailabilityStatus::Available->value);
});

test('a booking on the last day of the week (sunday) is still picked up as booked', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->create(['company_id' => $company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);

    $booking = Booking::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $company->id,
        'date' => '2026-08-16',
        'period' => 'full_day',
    ]);

    $rows = CandidateWeeklyAvailability::forWeek($candidate, Carbon::parse('2026-08-10'));

    $sunday = collect($rows)->firstWhere('date', '2026-08-16');
    expect($sunday['status'])->toBe(CandidateAvailabilityStatus::Booked->value);
    expect($sunday['editable'])->toBeFalse();
});

test('a day with an active booking is forced to booked and not editable, overriding any stored status', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->create(['company_id' => $company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);

    $candidate->availabilities()->create([
        'date' => '2026-08-12',
        'status' => CandidateAvailabilityStatus::Available->value,
    ]);

    $booking = Booking::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $company->id,
        'date' => '2026-08-12',
        'period' => 'full_day',
    ]);

    $rows = CandidateWeeklyAvailability::forWeek($candidate, Carbon::parse('2026-08-10'));

    $wednesday = collect($rows)->firstWhere('date', '2026-08-12');
    expect($wednesday['status'])->toBe(CandidateAvailabilityStatus::Booked->value);
    expect($wednesday['editable'])->toBeFalse();
});

test('a cancelled booking day does not force the booked status', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->create(['company_id' => $company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);

    $booking = Booking::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $company->id,
        'date' => '2026-08-12',
        'period' => 'full_day',
        'cancelled_at' => now(),
    ]);

    $rows = CandidateWeeklyAvailability::forWeek($candidate, Carbon::parse('2026-08-10'));

    $wednesday = collect($rows)->firstWhere('date', '2026-08-12');
    expect($wednesday['status'])->toBeNull();
    expect($wednesday['editable'])->toBeTrue();
});

test('it works the same way for a healthcare candidate', function () {
    $company = Company::factory()->create();
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $company->id]);
    $candidate->availabilities()->create([
        'date' => '2026-08-10',
        'status' => CandidateAvailabilityStatus::NotAvailable->value,
    ]);

    $rows = CandidateWeeklyAvailability::forWeek($candidate, Carbon::parse('2026-08-10'));

    expect($rows[0]['status'])->toBe(CandidateAvailabilityStatus::NotAvailable->value);
});
