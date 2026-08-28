<?php

use App\Enums\BookingDayPeriod;
use App\Filament\Widgets\EducationConsultantLeaderboard;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
    Cache::put("user.{$this->user->id}.active_industry_id", 1);

    $this->company = $this->user->company;

    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id]);
    $this->client = Client::factory()->create(['company_id' => $this->company->id]);
    $this->candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
});

function createBookingCreatedAt(User $consultant, Client $client, EducationCandidate $candidate, JobTitle $jobTitle, string $createdAt): Booking
{
    $booking = Booking::factory()->create([
        'company_id' => $consultant->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
        'consultant_id' => $consultant->id,
    ]);
    $booking->forceFill(['created_at' => $createdAt])->save();

    return $booking;
}

function addLeaderboardDayPeriod(Booking $booking, string $date, array $attributes = []): void
{
    $booking->dayPeriods()->create(array_merge([
        'company_id' => $booking->company_id,
        'date' => $date,
        'period' => BookingDayPeriod::FullDay,
    ], $attributes));
}

test('month options include the current month, 11 months back, and 3 months ahead', function () {
    $options = Livewire::test(EducationConsultantLeaderboard::class)->instance()->monthOptions();

    $currentMonth = Carbon::now()->startOfMonth();

    expect($options)->toHaveKey($currentMonth->format('Y-m'))
        ->and($options)->toHaveKey($currentMonth->copy()->subMonths(11)->format('Y-m'))
        ->and($options)->toHaveKey($currentMonth->copy()->addMonths(3)->format('Y-m'))
        ->and($options)->not->toHaveKey($currentMonth->copy()->subMonths(12)->format('Y-m'))
        ->and($options)->not->toHaveKey($currentMonth->copy()->addMonths(4)->format('Y-m'));
});

test('a future month can be selected and shows its own weeks', function () {
    $component = Livewire::test(EducationConsultantLeaderboard::class);
    $futureMonth = Carbon::now()->addMonths(2)->format('Y-m');

    $component->set('selectedMonth', $futureMonth);

    $monthStart = Carbon::createFromFormat('Y-m-d', $futureMonth.'-01')->startOfMonth();

    expect($component->instance()->weeks())->not->toBeEmpty()
        ->and($component->instance()->weeks()->first()->lte($monthStart))->toBeTrue();
});

test('weeks returns complete monday-sunday weeks that overlap the selected month, never split at the boundary', function () {
    $component = Livewire::test(EducationConsultantLeaderboard::class);
    $component->set('selectedMonth', '2026-06');

    $weeks = $component->instance()->weeks();

    $monthStart = Carbon::createFromFormat('Y-m-d', '2026-06-01')->startOfMonth();
    $monthEnd = $monthStart->copy()->endOfMonth();

    expect($weeks)->not->toBeEmpty()
        ->and($weeks->every(fn (Carbon $week): bool => $week->dayOfWeekIso === 1))->toBeTrue()
        ->and($weeks->first()->copy()->endOfWeek(Carbon::SUNDAY)->gte($monthStart))->toBeTrue()
        ->and($weeks->first()->lte($monthStart))->toBeTrue()
        ->and($weeks->last()->lte($monthEnd))->toBeTrue()
        ->and($weeks->last()->copy()->addWeek()->gt($monthEnd))->toBeTrue();
});

test('it computes bookings booked in advance for the week, on for the week, and already on for next week', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $component = Livewire::test(EducationConsultantLeaderboard::class);
    $component->set('selectedMonth', '2026-06');

    $weeks = $component->instance()->weeks();
    $weekStart = $weeks[1];
    $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
    $nextWeekStart = $weekStart->copy()->addWeek();

    // Booked before this week started, and scheduled to run during it:
    // counts towards both "start" (booked in advance) and "current".
    $advanceBooking = createBookingCreatedAt($consultant, $this->client, $this->candidate, $this->jobTitle, $weekStart->copy()->subWeek()->toDateTimeString());
    addLeaderboardDayPeriod($advanceBooking, $weekStart->toDateString());

    // Booked after this week already started, but still scheduled to run
    // during it: counts towards "current" only, not "start".
    $lastMinuteBooking = createBookingCreatedAt($consultant, $this->client, $this->candidate, $this->jobTitle, $weekStart->copy()->addDay()->toDateTimeString());
    addLeaderboardDayPeriod($lastMinuteBooking, $weekEnd->toDateString());

    // Booked before this week started, but scheduled to run next week
    // instead: must not count towards this week's "start" or "current",
    // only "nextWeek".
    $nextWeekBooking = createBookingCreatedAt($consultant, $this->client, $this->candidate, $this->jobTitle, $weekStart->copy()->subWeek()->toDateTimeString());
    addLeaderboardDayPeriod($nextWeekBooking, $nextWeekStart->toDateString());

    $row = $component->instance()->leaderboard()->firstWhere('consultant.id', $consultant->id);
    $weekData = $row['weeks']->get($weekStart->toDateString());

    expect($weekData['start'])->toBe(1)
        ->and($weekData['current'])->toBe(2)
        ->and($weekData['nextWeek'])->toBe(1);
});

test('a single booking spanning multiple days in the same week counts once per day, not once per booking', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $component = Livewire::test(EducationConsultantLeaderboard::class);
    $component->set('selectedMonth', '2026-06');

    $weeks = $component->instance()->weeks();
    $weekStart = $weeks[1];

    // One booking, booked before the week started, with three day-periods
    // all falling inside the same week.
    $booking = createBookingCreatedAt($consultant, $this->client, $this->candidate, $this->jobTitle, $weekStart->copy()->subWeek()->toDateTimeString());
    addLeaderboardDayPeriod($booking, $weekStart->toDateString());
    addLeaderboardDayPeriod($booking, $weekStart->copy()->addDay()->toDateString());
    addLeaderboardDayPeriod($booking, $weekStart->copy()->addDays(2)->toDateString());

    $row = $component->instance()->leaderboard()->firstWhere('consultant.id', $consultant->id);
    $weekData = $row['weeks']->get($weekStart->toDateString());

    expect($weekData['current'])->toBe(3)
        ->and($weekData['start'])->toBe(3);
});

test('a single booking spanning two weeks contributes separately to each week its days fall in', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $component = Livewire::test(EducationConsultantLeaderboard::class);
    $component->set('selectedMonth', '2026-06');

    $weeks = $component->instance()->weeks();
    $weekStart = $weeks[1];
    $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
    $nextWeekStart = $weekStart->copy()->addWeek();

    // One booking, with two day-periods in $weekStart's week and one day-
    // period the following week.
    $booking = createBookingCreatedAt($consultant, $this->client, $this->candidate, $this->jobTitle, $weekStart->copy()->subWeek()->toDateTimeString());
    addLeaderboardDayPeriod($booking, $weekEnd->copy()->subDay()->toDateString());
    addLeaderboardDayPeriod($booking, $weekEnd->toDateString());
    addLeaderboardDayPeriod($booking, $nextWeekStart->toDateString());

    $row = $component->instance()->leaderboard()->firstWhere('consultant.id', $consultant->id);
    $weekData = $row['weeks']->get($weekStart->toDateString());

    expect($weekData['current'])->toBe(2)
        ->and($weekData['nextWeek'])->toBe(1);
});

test('cancelled days do not count towards the current or next week totals', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $component = Livewire::test(EducationConsultantLeaderboard::class);
    $component->set('selectedMonth', '2026-06');

    $weeks = $component->instance()->weeks();
    $weekStart = $weeks[1];

    $booking = createBookingCreatedAt($consultant, $this->client, $this->candidate, $this->jobTitle, $weekStart->copy()->subWeek()->toDateTimeString());
    addLeaderboardDayPeriod($booking, $weekStart->toDateString(), ['cancelled_at' => now()]);

    $row = $component->instance()->leaderboard()->firstWhere('consultant.id', $consultant->id);
    $weekData = $row['weeks']->get($weekStart->toDateString());

    expect($weekData['current'])->toBe(0);
});

test('the leaderboard is ordered by the highest current-week total', function () {
    $consultantA = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultantA->assignRole('consultant');
    $consultantB = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultantB->assignRole('consultant');

    $currentWeekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);

    $bookingA = createBookingCreatedAt($consultantA, $this->client, $this->candidate, $this->jobTitle, now()->toDateTimeString());
    addLeaderboardDayPeriod($bookingA, $currentWeekStart->toDateString());

    $bookingB1 = createBookingCreatedAt($consultantB, $this->client, $this->candidate, $this->jobTitle, now()->toDateTimeString());
    addLeaderboardDayPeriod($bookingB1, $currentWeekStart->toDateString());
    $bookingB2 = createBookingCreatedAt($consultantB, $this->client, $this->candidate, $this->jobTitle, now()->toDateTimeString());
    addLeaderboardDayPeriod($bookingB2, $currentWeekStart->copy()->addDay()->toDateString());

    $rows = Livewire::test(EducationConsultantLeaderboard::class)->instance()->leaderboard();

    expect($rows->first()['consultant']->id)->toBe($consultantB->id)
        ->and($rows->last()['consultant']->id)->toBe($consultantA->id);
});

test('isCurrentWeek correctly identifies the week containing today', function () {
    $component = Livewire::test(EducationConsultantLeaderboard::class)->instance();

    $currentWeekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);

    expect($component->isCurrentWeek($currentWeekStart))->toBeTrue()
        ->and($component->isCurrentWeek($currentWeekStart->copy()->subWeek()))->toBeFalse();
});

test('the widget renders successfully', function () {
    Livewire::test(EducationConsultantLeaderboard::class)->assertSuccessful();
});

test('the next-week rebook figure is only rendered for the current week column, not other weeks', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $component = Livewire::test(EducationConsultantLeaderboard::class);
    $instance = $component->instance();

    $currentWeekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
    $otherWeekStart = $instance->weeks()->first(fn (Carbon $week): bool => ! $instance->isCurrentWeek($week));

    // A booking scheduled for the week after $otherWeekStart gives that
    // (non-current) week column a nonzero "nextWeek" value in the
    // underlying data — it must still not be rendered.
    $otherWeekBooking = createBookingCreatedAt($consultant, $this->client, $this->candidate, $this->jobTitle, $otherWeekStart->copy()->subWeek()->toDateTimeString());
    addLeaderboardDayPeriod($otherWeekBooking, $otherWeekStart->copy()->addWeek()->toDateString());

    // A booking scheduled for the week after the current week — this one
    // must be rendered, since it's the current week's column.
    $currentWeekBooking = createBookingCreatedAt($consultant, $this->client, $this->candidate, $this->jobTitle, $currentWeekStart->copy()->subWeek()->toDateTimeString());
    addLeaderboardDayPeriod($currentWeekBooking, $currentWeekStart->copy()->addWeek()->toDateString());

    $html = Livewire::test(EducationConsultantLeaderboard::class)->html();

    expect(substr_count($html, 'Booking days already on for next week'))->toBe(1);
});

test('the before-this-week figure is only rendered for the current week column, not other weeks', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $component = Livewire::test(EducationConsultantLeaderboard::class);
    $instance = $component->instance();

    $currentWeekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
    $otherWeekStart = $instance->weeks()->first(fn (Carbon $week): bool => ! $instance->isCurrentWeek($week));

    // Booked before $otherWeekStart and scheduled to run during it — gives
    // that (non-current) week column a nonzero "start" value in the
    // underlying data; it must still not be rendered.
    $otherWeekBooking = createBookingCreatedAt($consultant, $this->client, $this->candidate, $this->jobTitle, $otherWeekStart->copy()->subDay()->toDateTimeString());
    addLeaderboardDayPeriod($otherWeekBooking, $otherWeekStart->toDateString());

    // Booked before the current week and scheduled to run during it — this
    // one must be rendered, since it's the current week's column.
    $currentWeekBooking = createBookingCreatedAt($consultant, $this->client, $this->candidate, $this->jobTitle, $currentWeekStart->copy()->subDay()->toDateTimeString());
    addLeaderboardDayPeriod($currentWeekBooking, $currentWeekStart->toDateString());

    $html = Livewire::test(EducationConsultantLeaderboard::class)->html();

    expect(substr_count($html, "This week's booking days that were booked in advance, before the week started"))->toBe(1);
});
