<?php

use App\Enums\BookingDayPeriod;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Services\Booking\BookingDayPeriods;

test('unitsFor computes the real elapsed hours for a Waking Night shift crossing midnight', function () {
    // 22:00 -> 07:00 is a 9-hour shift; time_from/time_to have no date
    // component, so naively diffing them same-day would give 15 hours
    // (24 - 9) instead.
    $day = new BookingDay([
        'period' => BookingDayPeriod::WakingNight,
        'time_from' => '22:00',
        'time_to' => '07:00',
    ]);

    expect(BookingDayPeriods::unitsFor($day))->toBe(9.0);
});

test('unitsFor still computes correctly for a same-day Hours shift', function () {
    $day = new BookingDay([
        'period' => BookingDayPeriod::Hours,
        'time_from' => '09:00',
        'time_to' => '17:00',
    ]);

    expect(BookingDayPeriods::unitsFor($day))->toBe(8.0);
});

test('rows() reports the real elapsed hours for an overnight Waking Night day', function () {
    $booking = Booking::factory()->create([
        'waking_night_rate' => 15,
    ]);

    $booking->dayPeriods()->create([
        'company_id' => $booking->company_id,
        'date' => now()->toDateString(),
        'period' => BookingDayPeriod::WakingNight,
        'time_from' => '22:00',
        'time_to' => '07:00',
    ]);

    $rows = BookingDayPeriods::rows($booking, 'pay');

    expect($rows[0]['hours'])->toBe(9.0);
});
