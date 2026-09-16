<?php

use App\Enums\BookingDayPeriod;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\EducationCandidate;
use App\Services\Booking\MarginCalculator;

test('forBooking applies the PAYE on-cost and scales an hours-period day by hours worked', function () {
    $candidate = EducationCandidate::factory()->create(['payment_method' => PaymentMethod::Paye]);

    $booking = Booking::factory()->create([
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'hourly_rate' => 10,
        'hourly_charge_rate' => 20,
    ]);

    $booking->dayPeriods()->create([
        'company_id' => $booking->company_id,
        'date' => '2026-01-05',
        'period' => BookingDayPeriod::Hours,
        'time_from' => '09:00',
        'time_to' => '13:00',
    ]);

    $breakdown = MarginCalculator::forBooking($booking->fresh());

    // 4 hours at £10/£20 = £40 pay, £80 charge, plus 15% PAYE on-cost on pay.
    expect($breakdown['totalPay'])->toBe(40.0)
        ->and($breakdown['totalCharge'])->toBe(80.0)
        ->and($breakdown['oncosts'])->toBe(6.0)
        ->and($breakdown['margin'])->toBe(34.0);
});

test('forBooking ignores an umbrella candidates on-costs and excludes cancelled days', function () {
    $candidate = EducationCandidate::factory()->create(['payment_method' => PaymentMethod::Umbrella]);

    $booking = Booking::factory()->create([
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    $booking->dayPeriods()->create([
        'company_id' => $booking->company_id,
        'date' => '2026-01-05',
        'period' => BookingDayPeriod::FullDay,
    ]);

    $booking->dayPeriods()->create([
        'company_id' => $booking->company_id,
        'date' => '2026-01-06',
        'period' => BookingDayPeriod::FullDay,
        'cancelled_at' => now(),
    ]);

    $breakdown = MarginCalculator::forBooking($booking->fresh());

    expect($breakdown['totalPay'])->toBe(100.0)
        ->and($breakdown['totalCharge'])->toBe(150.0)
        ->and($breakdown['oncosts'])->toBe(0.0)
        ->and($breakdown['margin'])->toBe(50.0);
});
