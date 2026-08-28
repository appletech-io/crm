<?php

use App\Enums\CandidateAvailabilityStatus;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Services\Candidates\CandidateMonthlyAvailability;
use Illuminate\Support\Carbon;

test('it returns one row per day for the calendar month containing the given date', function () {
    $company = Company::factory()->create();
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);

    // August 2026 has 31 days.
    $rows = CandidateMonthlyAvailability::forMonth($candidate, Carbon::parse('2026-08-12'));

    expect($rows)->toHaveCount(31);
    expect($rows[0]['date'])->toBe('2026-08-01');
    expect($rows[30]['date'])->toBe('2026-08-31');
});

test('a day with no stored availability has a null status and is editable', function () {
    $company = Company::factory()->create();
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);

    $rows = CandidateMonthlyAvailability::forMonth($candidate, Carbon::parse('2026-08-01'));

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

    $rows = CandidateMonthlyAvailability::forMonth($candidate, Carbon::parse('2026-08-01'));

    $day = collect($rows)->firstWhere('date', '2026-08-11');
    expect($day['status'])->toBe(CandidateAvailabilityStatus::AvailableAm->value);
    expect($day['editable'])->toBeTrue();
});

test('a stored availability on the last day of the month is still picked up', function () {
    // Regression test: the "date" cast reads back as date-only but writes a
    // full datetime string, so a naive whereBetween on the raw column would
    // silently exclude the month's final day.
    $company = Company::factory()->create();
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);
    $candidate->availabilities()->create([
        'date' => '2026-08-31',
        'status' => CandidateAvailabilityStatus::Available->value,
    ]);

    $rows = CandidateMonthlyAvailability::forMonth($candidate, Carbon::parse('2026-08-01'));

    $lastDay = collect($rows)->firstWhere('date', '2026-08-31');
    expect($lastDay['status'])->toBe(CandidateAvailabilityStatus::Available->value);
});

test('a stored availability just outside the month is not picked up', function () {
    $company = Company::factory()->create();
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);
    $candidate->availabilities()->create([
        'date' => '2026-09-01',
        'status' => CandidateAvailabilityStatus::Available->value,
    ]);

    $rows = CandidateMonthlyAvailability::forMonth($candidate, Carbon::parse('2026-08-01'));

    expect(collect($rows)->pluck('date'))->not->toContain('2026-09-01');
});

test('a booking on the last day of the month is still picked up as booked', function () {
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
        'date' => '2026-08-31',
        'period' => 'full_day',
    ]);

    $rows = CandidateMonthlyAvailability::forMonth($candidate, Carbon::parse('2026-08-01'));

    $lastDay = collect($rows)->firstWhere('date', '2026-08-31');
    expect($lastDay['status'])->toBe(CandidateAvailabilityStatus::Booked->value);
    expect($lastDay['editable'])->toBeFalse();
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

    $rows = CandidateMonthlyAvailability::forMonth($candidate, Carbon::parse('2026-08-01'));

    $day = collect($rows)->firstWhere('date', '2026-08-12');
    expect($day['status'])->toBe(CandidateAvailabilityStatus::Booked->value);
    expect($day['editable'])->toBeFalse();
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

    $rows = CandidateMonthlyAvailability::forMonth($candidate, Carbon::parse('2026-08-01'));

    $day = collect($rows)->firstWhere('date', '2026-08-12');
    expect($day['status'])->toBeNull();
    expect($day['editable'])->toBeTrue();
});

test('it works the same way for a healthcare candidate', function () {
    $company = Company::factory()->create();
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $company->id]);
    $candidate->availabilities()->create([
        'date' => '2026-08-01',
        'status' => CandidateAvailabilityStatus::NotAvailable->value,
    ]);

    $rows = CandidateMonthlyAvailability::forMonth($candidate, Carbon::parse('2026-08-01'));

    expect($rows[0]['status'])->toBe(CandidateAvailabilityStatus::NotAvailable->value);
});
