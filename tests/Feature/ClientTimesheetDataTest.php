<?php

use App\Enums\BookingDayPeriod;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\JobTitle;
use App\Models\User;
use App\Services\Payroll\ClientTimesheetData;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->client = Client::factory()->create(['company_id' => $this->company->id, 'name' => 'Oakwood School']);
});

test('it groups rows under one contractor section per candidate, sorted by date', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);
    $jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id, 'name' => 'Teaching Assistant']);

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
        'day_charge_rate' => 150,
    ]);

    $tuesday = $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => '2026-09-08',
        'period' => BookingDayPeriod::FullDay,
    ]);
    $monday = $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => '2026-09-07',
        'period' => BookingDayPeriod::FullDay,
    ]);

    $days = collect([$tuesday->fresh(), $monday->fresh()]);

    $contractors = ClientTimesheetData::contractorsFor($days);

    expect($contractors)->toHaveCount(1)
        ->and($contractors[0]['name'])->toBe('Jane Doe')
        ->and($contractors[0]['rows'])->toHaveCount(2)
        ->and($contractors[0]['rows'][0]['date']->toDateString())->toBe('2026-09-07')
        ->and($contractors[0]['rows'][1]['date']->toDateString())->toBe('2026-09-08')
        ->and($contractors[0]['rows'][0]['job_title'])->toBe('Teaching Assistant')
        ->and($contractors[0]['rows'][0]['rate'])->toBe(150.0);
});

test('a candidate booked twice at the same client in the same week is one contractor section, not two', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $firstBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);
    $secondBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);

    $day1 = $firstBooking->dayPeriods()->create([
        'company_id' => $this->company->id, 'date' => '2026-09-07', 'period' => BookingDayPeriod::FullDay,
    ]);
    $day2 = $secondBooking->dayPeriods()->create([
        'company_id' => $this->company->id, 'date' => '2026-09-08', 'period' => BookingDayPeriod::FullDay,
    ]);

    $contractors = ClientTimesheetData::contractorsFor(collect([$day1->fresh(), $day2->fresh()]));

    expect($contractors)->toHaveCount(1)
        ->and($contractors[0]['rows'])->toHaveCount(2);
});

test('two different candidates produce two separate contractor sections, sorted by name', function () {
    $zed = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'first_name' => 'Zed', 'last_name' => 'Zebra']);
    $amy = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'first_name' => 'Amy', 'last_name' => 'Apple']);

    $zedBooking = Booking::factory()->create([
        'company_id' => $this->company->id, 'client_id' => $this->client->id,
        'candidate_id' => $zed->id, 'candidate_type' => EducationCandidate::class,
    ]);
    $amyBooking = Booking::factory()->create([
        'company_id' => $this->company->id, 'client_id' => $this->client->id,
        'candidate_id' => $amy->id, 'candidate_type' => EducationCandidate::class,
    ]);

    $zedDay = $zedBooking->dayPeriods()->create(['company_id' => $this->company->id, 'date' => '2026-09-07', 'period' => BookingDayPeriod::FullDay]);
    $amyDay = $amyBooking->dayPeriods()->create(['company_id' => $this->company->id, 'date' => '2026-09-07', 'period' => BookingDayPeriod::FullDay]);

    $contractors = ClientTimesheetData::contractorsFor(collect([$zedDay->fresh(), $amyDay->fresh()]));

    expect($contractors)->toHaveCount(2)
        ->and($contractors[0]['name'])->toBe('Amy Apple')
        ->and($contractors[1]['name'])->toBe('Zed Zebra');
});

test('a contractor whose every day is approved shows the latest approver and date', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $approver = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Ashley Greaves']);

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id, 'client_id' => $this->client->id,
        'candidate_id' => $candidate->id, 'candidate_type' => EducationCandidate::class,
    ]);

    $booking->dayPeriods()->create([
        'company_id' => $this->company->id, 'date' => '2026-09-07', 'period' => BookingDayPeriod::FullDay,
        'approved_at' => '2026-09-07 09:00:00', 'approved_by_user_id' => $approver->id,
    ]);
    $latest = $booking->dayPeriods()->create([
        'company_id' => $this->company->id, 'date' => '2026-09-08', 'period' => BookingDayPeriod::FullDay,
        'approved_at' => '2026-09-08 14:30:00', 'approved_by_user_id' => $approver->id,
    ]);

    $days = $booking->dayPeriods()->with('approvedBy')->get();

    $contractors = ClientTimesheetData::contractorsFor($days);

    expect($contractors[0]['approval']['name'])->toBe('Ashley Greaves')
        ->and($contractors[0]['approval']['date']->toDateTimeString())->toBe($latest->approved_at->toDateTimeString());
});

test('a contractor with any unapproved day has no approval block at all', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $approver = User::factory()->create(['company_id' => $this->company->id]);

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id, 'client_id' => $this->client->id,
        'candidate_id' => $candidate->id, 'candidate_type' => EducationCandidate::class,
    ]);

    $booking->dayPeriods()->create([
        'company_id' => $this->company->id, 'date' => '2026-09-07', 'period' => BookingDayPeriod::FullDay,
        'approved_at' => now(), 'approved_by_user_id' => $approver->id,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->company->id, 'date' => '2026-09-08', 'period' => BookingDayPeriod::FullDay,
    ]);

    $days = $booking->dayPeriods()->with('approvedBy')->get();

    $contractors = ClientTimesheetData::contractorsFor($days);

    expect($contractors[0]['approval'])->toBeNull();
});

test('a deleted candidate\'s name is suffixed', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'first_name' => 'Jane', 'last_name' => 'Doe']);

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id, 'client_id' => $this->client->id,
        'candidate_id' => $candidate->id, 'candidate_type' => EducationCandidate::class,
    ]);

    $day = $booking->dayPeriods()->create(['company_id' => $this->company->id, 'date' => '2026-09-07', 'period' => BookingDayPeriod::FullDay]);

    $candidate->delete();

    $day = $day->fresh();
    $day->booking->setRelation('candidate', $candidate);

    $contractors = ClientTimesheetData::contractorsFor(collect([$day]));

    expect($contractors[0]['name'])->toBe('Jane Doe (deleted)');
});

test('a cancelled day\'s rate is not shown', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id, 'client_id' => $this->client->id,
        'candidate_id' => $candidate->id, 'candidate_type' => EducationCandidate::class,
        'day_charge_rate' => 150,
    ]);

    $day = $booking->dayPeriods()->create([
        'company_id' => $this->company->id, 'date' => '2026-09-07', 'period' => BookingDayPeriod::FullDay,
        'cancelled_at' => now(),
    ]);

    $contractors = ClientTimesheetData::contractorsFor(collect([$day->fresh()]));

    expect($contractors[0]['rows'][0]['rate'])->toBeNull();
});
