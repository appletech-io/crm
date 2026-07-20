<?php

use App\Enums\TimesheetFrequency;
use App\Models\Company;
use App\Services\Booking\TimesheetPeriod;
use Illuminate\Support\Carbon;

function companyWithFrequency(TimesheetFrequency $frequency, ?int $dayOfMonth = null): Company
{
    return Company::factory()->create([
        'timesheet_frequency' => $frequency,
        'timesheet_day_of_month' => $dayOfMonth,
    ]);
}

test('weekly period runs saturday to friday and contains a mid-week date', function () {
    $company = companyWithFrequency(TimesheetFrequency::Weekly);

    // Wednesday 2026-07-22.
    $period = TimesheetPeriod::containing($company, Carbon::parse('2026-07-22'));

    expect($period['start']->toDateString())->toBe('2026-07-18') // Saturday
        ->and($period['end']->toDateString())->toBe('2026-07-24'); // Friday
});

test('weekly period containing the friday cutoff itself ends on that friday', function () {
    $company = companyWithFrequency(TimesheetFrequency::Weekly);

    $period = TimesheetPeriod::containing($company, Carbon::parse('2026-07-24'));

    expect($period['start']->toDateString())->toBe('2026-07-18')
        ->and($period['end']->toDateString())->toBe('2026-07-24');
});

test('weekly period containing the saturday start itself starts on that saturday', function () {
    $company = companyWithFrequency(TimesheetFrequency::Weekly);

    $period = TimesheetPeriod::containing($company, Carbon::parse('2026-07-18'));

    expect($period['start']->toDateString())->toBe('2026-07-18')
        ->and($period['end']->toDateString())->toBe('2026-07-24');
});

test('biweekly periods form a stable 14 day grid where both weeks map to the same period', function () {
    $company = companyWithFrequency(TimesheetFrequency::Biweekly);

    $firstWeekDate = Carbon::parse('2026-07-18'); // Saturday of week 1
    $secondWeekDate = Carbon::parse('2026-07-23'); // Thursday of week 2 of the same block

    $periodA = TimesheetPeriod::containing($company, $firstWeekDate);
    $periodB = TimesheetPeriod::containing($company, $secondWeekDate);

    $daysInPeriod = Carbon::parse($periodA['start']->toDateString())->diffInDays(Carbon::parse($periodA['end']->toDateString()));

    expect($periodA['start']->toDateString())->toBe($periodB['start']->toDateString())
        ->and($periodA['end']->toDateString())->toBe($periodB['end']->toDateString())
        ->and($daysInPeriod)->toBe(13.0);
});

test('biweekly periods do not overlap and follow directly on from each other', function () {
    $company = companyWithFrequency(TimesheetFrequency::Biweekly);

    $period = TimesheetPeriod::containing($company, Carbon::parse('2026-07-18'));
    $next = TimesheetPeriod::next($company, $period['start']);

    expect($next['start']->toDateString())->toBe($period['end']->copy()->addDay()->toDateString());
});

test('monthly period runs from the anchor day to the day before it next month', function () {
    $company = companyWithFrequency(TimesheetFrequency::Monthly, 15);

    $period = TimesheetPeriod::containing($company, Carbon::parse('2026-07-20'));

    expect($period['start']->toDateString())->toBe('2026-07-15')
        ->and($period['end']->toDateString())->toBe('2026-08-14');
});

test('monthly period before the anchor day falls into the previous months period', function () {
    $company = companyWithFrequency(TimesheetFrequency::Monthly, 15);

    $period = TimesheetPeriod::containing($company, Carbon::parse('2026-07-10'));

    expect($period['start']->toDateString())->toBe('2026-06-15')
        ->and($period['end']->toDateString())->toBe('2026-07-14');
});

test('monthly period clamps a day 31 anchor for short months', function () {
    $company = companyWithFrequency(TimesheetFrequency::Monthly, 31);

    // February 2026 has 28 days, so the period should start on the 28th.
    $period = TimesheetPeriod::containing($company, Carbon::parse('2026-02-28'));

    expect($period['start']->toDateString())->toBe('2026-02-28')
        ->and($period['end']->toDateString())->toBe('2026-03-30');
});

test('monthly period handles a leap year february correctly for a day 29 anchor', function () {
    $company = companyWithFrequency(TimesheetFrequency::Monthly, 29);

    // 2028 is a leap year.
    $period = TimesheetPeriod::containing($company, Carbon::parse('2028-02-29'));

    expect($period['start']->toDateString())->toBe('2028-02-29');
});

test('next and previous are inverses of each other for all frequencies', function (TimesheetFrequency $frequency) {
    $company = companyWithFrequency($frequency, 10);

    $period = TimesheetPeriod::containing($company, Carbon::parse('2026-07-20'));
    $next = TimesheetPeriod::next($company, $period['start']);
    $backAgain = TimesheetPeriod::previous($company, $next['start']);

    expect($backAgain['start']->toDateString())->toBe($period['start']->toDateString())
        ->and($backAgain['end']->toDateString())->toBe($period['end']->toDateString());
})->with([
    'weekly' => [TimesheetFrequency::Weekly],
    'biweekly' => [TimesheetFrequency::Biweekly],
    'monthly' => [TimesheetFrequency::Monthly],
]);

test('selectable dates between a range returns one cutoff date per overlapping weekly period', function () {
    $company = companyWithFrequency(TimesheetFrequency::Weekly);

    $dates = TimesheetPeriod::selectableDatesBetween(
        $company,
        Carbon::parse('2026-07-01'),
        Carbon::parse('2026-07-31'),
    );

    expect($dates)->toEqual(['2026-07-03', '2026-07-10', '2026-07-17', '2026-07-24', '2026-07-31']);
});

test('selectable dates between a range for a monthly client returns one date per month', function () {
    $company = companyWithFrequency(TimesheetFrequency::Monthly, 15);

    $dates = TimesheetPeriod::selectableDatesBetween(
        $company,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-08-31'),
    );

    expect($dates)->toEqual(['2026-06-14', '2026-07-14', '2026-08-14']);
});
