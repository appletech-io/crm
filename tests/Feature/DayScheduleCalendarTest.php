<?php

use App\Filament\Forms\Components\DayScheduleCalendar;

test('hours is enabled by default, matching the existing consultant-facing Booking form', function () {
    $field = DayScheduleCalendar::make('day_periods');

    expect($field->isHoursEnabled())->toBeTrue();
});

test('hours can be disabled for the client-facing booking-request modal', function () {
    $field = DayScheduleCalendar::make('day_periods')->hoursEnabled(false);

    expect($field->isHoursEnabled())->toBeFalse();
});

test('hoursEnabled accepts a closure', function () {
    $field = DayScheduleCalendar::make('day_periods')->hoursEnabled(fn (): bool => false);

    expect($field->isHoursEnabled())->toBeFalse();
});
