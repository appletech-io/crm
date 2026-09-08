<?php

namespace App\Enums;

use App\Models\BookingDay;

/**
 * Where a single booking day stands in the client-approval side of payroll.
 * Derived from the day's timestamps rather than stored as a column — see
 * {@see BookingDay::payrollStatus()} — with Disputed taking
 * precedence over Approved, since a day can carry both and the dispute is
 * the part that still needs resolving.
 */
enum PayrollStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Approved = 'approved';
    case Disputed = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Sent => 'Sent',
            self::Approved => 'Approved',
            self::Disputed => 'Disputed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Sent => 'info',
            self::Approved => 'success',
            self::Disputed => 'danger',
        };
    }

    /**
     * True for every status except a clean approval — the client either
     * hasn't responded or has actively contested the day.
     */
    public function isAwaitingApproval(): bool
    {
        return $this !== self::Approved;
    }
}
