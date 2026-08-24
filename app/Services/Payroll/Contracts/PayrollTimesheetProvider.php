<?php

namespace App\Services\Payroll\Contracts;

use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\Client;
use App\Models\PaymentProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface PayrollTimesheetProvider
{
    public function upsertClient(Client $client): void;

    /**
     * A payment provider (the candidate's Ltd/umbrella company) has no
     * standalone create endpoint on every provider's API — some create it
     * only as a side effect of upserting a candidate. Implementations that
     * need a separate call make it here; others may just resolve/persist
     * the external ID ready for upsertCandidate() to reference.
     */
    public function upsertPaymentProvider(PaymentProvider $paymentProvider): void;

    /** @param  Model  $candidate  EducationCandidate|HealthcareCandidate */
    public function upsertCandidate(Model $candidate): void;

    /**
     * Registers the booking's consultant against the provider so a
     * placement can be attributed to them instead of falling back to
     * whatever "default" consultant the provider assigns when none is set.
     */
    public function upsertConsultant(User $consultant): void;

    public function upsertPlacement(Booking $booking): void;

    /** @param  Collection<int, BookingDay>  $approvedDays */
    public function submitTimesheet(Booking $booking, Collection $approvedDays): void;
}
