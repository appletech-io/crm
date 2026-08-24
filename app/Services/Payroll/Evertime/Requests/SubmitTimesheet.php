<?php

namespace App\Services\Payroll\Evertime\Requests;

use App\Enums\BookingDayPeriod;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Services\Payroll\Evertime\EvertimeClient;
use Illuminate\Support\Collection;

/** POST /timesheets */
class SubmitTimesheet
{
    public function __construct(private readonly EvertimeClient $client) {}

    /** @param  Collection<int, BookingDay>  $approvedDays */
    public function handle(Booking $booking, Collection $approvedDays, string $placementId, string $periodEndDate, string $approverContactId): void
    {
        $timeEntries = $approvedDays
            ->map(fn (BookingDay $day): array => $this->timeEntryFor($day))
            ->values()
            ->all();

        $this->client->post('/timesheets', [
            'PlacementId' => $placementId,
            'PeriodEndDate' => $periodEndDate,
            'TimeEntries' => $timeEntries,
            // The live account rejects a request with only
            // ApproverClientContactId ("Please supply ApproverContactId"),
            // so this field is what's actually required here.
            'ApproverContactId' => $approverContactId,
            // Optional — recorded into the timesheet's notes in Evertime, so
            // a submission can be traced back to the originating booking.
            'ExternalTimesheetId' => "BOOKING-{$booking->id}-{$periodEndDate}",
            // Deliberately NOT sending TimesheetStatus. It defaults to 5
            // (Approved) when omitted, which is what we always want (we only
            // ever submit once the client has already approved these days).
            // Confirmed live: supplying ApproverContactId together with an
            // *explicit* TimesheetStatus key — any value, including 5 itself
            // — reliably returns "500 An internal server error occurred",
            // while the exact same request with TimesheetStatus simply
            // omitted returns 200. That's a bug on Evertime's side (reported
            // to their support), not a payload problem — this omission is
            // the working workaround, not an oversight.
        ]);
    }

    /** @return array<string, mixed> */
    private function timeEntryFor(BookingDay $day): array
    {
        if ($day->period === BookingDayPeriod::Hours) {
            // Confirmed live: Start/End times are only accepted against a
            // rate whose RateType is genuinely "Timesheet Hours" — sending
            // them against STD (Timesheet Days) is rejected outright
            // ("Can only supply Start/End Time for Timesheet Hours...").
            // STH is that hourly rate code.
            return [
                'RateId' => 'STH',
                'EntryDate' => $day->date->toDateString(),
                'StartTime' => $day->time_from,
                'EndTime' => $day->time_to,
                'BreakHours' => 0,
            ];
        }

        return [
            'RateId' => 'STD',
            'EntryDate' => $day->date->toDateString(),
            // Confirmed live: Units scales STD's flat day rate (e.g. 0.5
            // units against a £107.19 day rate correctly stores as a
            // half-day amount) — per-entry PayRate/ChargeRate is rejected
            // outright for non-expense entries, so Units is the only way to
            // represent a half day here.
            'Units' => match ($day->period) {
                BookingDayPeriod::Am, BookingDayPeriod::Pm => 0.5,
                default => 1,
            },
        ];
    }
}
