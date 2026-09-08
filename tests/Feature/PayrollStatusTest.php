<?php

use App\Enums\PayrollStatus;
use App\Enums\PayrollStatusFilter;
use App\Models\BookingDay;

test('a booking day with no confirmation sent is pending', function () {
    expect(new BookingDay)->payrollStatus()->toBe(PayrollStatus::Pending);
});

test('a booking day is sent once the confirmation has gone out', function () {
    $day = new BookingDay(['payroll_confirmation_sent_at' => now()]);

    expect($day)->payrollStatus()->toBe(PayrollStatus::Sent);
});

test('a booking day is approved once the client signs it off', function () {
    $day = new BookingDay(['payroll_confirmation_sent_at' => now(), 'approved_at' => now()]);

    expect($day)->payrollStatus()->toBe(PayrollStatus::Approved);
});

test('a dispute outranks an approval sitting alongside it', function () {
    $day = new BookingDay([
        'payroll_confirmation_sent_at' => now(),
        'approved_at' => now(),
        'disputed_at' => now(),
    ]);

    expect($day)->payrollStatus()->toBe(PayrollStatus::Disputed);
});

test('every status except a clean approval counts as awaiting approval', function () {
    expect(PayrollStatus::Pending->isAwaitingApproval())->toBeTrue()
        ->and(PayrollStatus::Sent->isAwaitingApproval())->toBeTrue()
        ->and(PayrollStatus::Disputed->isAwaitingApproval())->toBeTrue()
        ->and(PayrollStatus::Approved->isAwaitingApproval())->toBeFalse();
});

test('the payroll status filter offers the three slices a consultant chases by', function () {
    expect(PayrollStatusFilter::options())->toBe([
        'awaiting' => 'Awaiting approval',
        'approved' => 'Approved',
        'disputed' => 'Disputed',
    ]);
});

test('each filter slice explains an empty period in its own terms', function () {
    expect(PayrollStatusFilter::AwaitingApproval->emptyStateHeading())->toBe('Nothing left to approve for this period')
        ->and(PayrollStatusFilter::Approved->emptyStateHeading())->toBe('Nothing approved yet for this period')
        ->and(PayrollStatusFilter::Disputed->emptyStateHeading())->toBe('Nothing disputed for this period');
});
