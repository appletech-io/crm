<?php

use App\Enums\PaymentMethod;
use App\Services\Booking\MarginCalculator;

test('a PAYE candidate has the employer on-cost rate applied to pay', function () {
    $breakdown = MarginCalculator::breakdown(totalPay: 100.0, totalCharge: 150.0, paymentMethod: PaymentMethod::Paye);

    expect($breakdown['oncosts'])->toBe(15.0)
        ->and($breakdown['margin'])->toBe(35.0)
        ->and($breakdown['marginPercent'])->toBe(23.3);
});

test('an umbrella candidate has no on-cost applied', function () {
    $breakdown = MarginCalculator::breakdown(totalPay: 100.0, totalCharge: 150.0, paymentMethod: PaymentMethod::Umbrella);

    expect($breakdown['oncosts'])->toBe(0.0)
        ->and($breakdown['margin'])->toBe(50.0);
});

test('no payment method set is treated the same as no on-cost', function () {
    $breakdown = MarginCalculator::breakdown(totalPay: 100.0, totalCharge: 150.0, paymentMethod: null);

    expect($breakdown['oncosts'])->toBe(0.0)
        ->and($breakdown['margin'])->toBe(50.0);
});

test('marginPercent is 0 when there is no charge to divide by', function () {
    $breakdown = MarginCalculator::breakdown(totalPay: 0.0, totalCharge: 0.0, paymentMethod: PaymentMethod::Paye);

    expect($breakdown['marginPercent'])->toBe(0.0);
});
