<?php

namespace App\Services\Payroll\Evertime\Requests;

use App\Models\Booking;
use App\Services\Payroll\Evertime\EvertimeClient;

/** POST /placements */
class UpsertPlacement
{
    public function __construct(private readonly EvertimeClient $client) {}

    public function handle(
        Booking $booking,
        string $placementId,
        string $candidateId,
        string $clientId,
        string $locationId,
        string $contactId,
        ?string $consultantId,
    ): void {
        $startDate = ($booking->start_date ?? now())->toDateString();

        $payload = [
            'PlacementId' => $placementId,
            'CandidateId' => $candidateId,
            'ClientId' => $clientId,
            'TimesheetMethod' => 'Manual',
            'StartDate' => $startDate,
            'EndDate' => $booking->end_date?->toDateString(),
            'PayCurrency' => 'GBP',
            'ChargeCurrency' => 'GBP',
            'JobTitle' => $booking->jobTitle?->name,
            'ContractType' => 'Temp',
            'InvoiceLocationId' => $locationId,
            // Docs mark this optional, but the live account rejects
            // placement creation ("No InvoiceContactId specified") without
            // it — reuse the same contact already registered on the client.
            'InvoiceContactId' => $contactId,
            // A contact existing on the client isn't enough on its own — the
            // timesheet's ApproverContactId/ApproverClientContactId must be
            // an active contact specifically associated to *this placement*
            // ("An active client contact was not found... associated to
            // PlacementId..."), so it needs registering here too.
            'PrimaryApproverContactId' => $contactId,
            'WorkLocationId' => $locationId,
            'InvoiceFrequency' => 'Weekly',
            'TimesheetFrequency' => 'Weekly',
            'PlacementStatus' => 'Open',
            'PlacementRates' => $this->placementRates($booking, $startDate),
        ];

        // Without this, Evertime silently falls back to its own "DEFAULT"
        // consultant rather than attributing the placement to the booking's
        // actual consultant.
        if ($consultantId) {
            $payload['Consultants'] = [[
                'ConsultantId' => $consultantId,
                'Share' => 100,
            ]];
        }

        $this->client->post('/placements', $payload);
    }

    /**
     * RateId isn't something we invent per booking — it's one of a small,
     * fixed set of rate codes this agency already configured once in
     * Evertime's own "Rates" tab, each with a permanent RateType (confirmed
     * live via GET /agency/rates: STD = Timesheet Days, STH = Timesheet
     * Hours, plus OT/EXP/MIL which this app doesn't use). What varies per
     * placement is only the PayRate/ChargeRate figures attached to whichever
     * of those codes apply — so a placement gets an STD entry if it has a
     * day/half-day rate, an STH entry if it has an hourly rate, or both if
     * the booking mixes day and hourly work.
     *
     * @return array<int, array<string, mixed>>
     */
    private function placementRates(Booking $booking, string $startDate): array
    {
        $rates = [];

        if ($booking->day_rate !== null || $booking->day_charge_rate !== null || $booking->half_day_rate !== null || $booking->half_day_charge_rate !== null) {
            $rates[] = [
                'RateId' => 'STD',
                'RateType' => 'TimesheetDays',
                // half_day_rate/half_day_charge_rate are deliberately NOT
                // sent here — Evertime's STD rate only has one PayRate/
                // ChargeRate per placement, shared by every STD TimeEntry
                // regardless of Units, so a half day is represented as
                // Units: 0.5 against this same day rate (see
                // SubmitTimesheet::timeEntryFor()) rather than its own
                // figure. Confirmed live this can differ from an agency's
                // actual (non-proportional) half-day rate — accepted as a
                // known trade-off rather than adding a second Evertime rate
                // code solely for exact half-day amounts. day_rate falls
                // back to half_day_rate only for a booking that has
                // half-day pricing but no day rate at all.
                'PayRate' => $booking->day_rate ?? $booking->half_day_rate ?? 0,
                'ChargeRate' => $booking->day_charge_rate ?? $booking->half_day_charge_rate ?? 0,
                'StartDate' => $startDate,
            ];
        }

        if ($booking->hourly_rate !== null || $booking->hourly_charge_rate !== null) {
            $rates[] = [
                'RateId' => 'STH',
                'RateType' => 'TimesheetHours',
                'PayRate' => $booking->hourly_rate ?? 0,
                'ChargeRate' => $booking->hourly_charge_rate ?? 0,
                'StartDate' => $startDate,
            ];
        }

        return $rates ?: [[
            'RateId' => 'STD',
            'RateType' => 'TimesheetDays',
            'PayRate' => 0,
            'ChargeRate' => 0,
            'StartDate' => $startDate,
        ]];
    }
}
