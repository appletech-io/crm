<?php

use App\Enums\BookingDayPeriod;

test('every case has a label', function () {
    foreach (BookingDayPeriod::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});
