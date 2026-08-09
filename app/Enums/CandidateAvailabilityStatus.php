<?php

namespace App\Enums;

enum CandidateAvailabilityStatus: string
{
    case Available = 'available';
    case AvailableAm = 'available_am';
    case AvailablePm = 'available_pm';
    case NotAvailable = 'not_available';
    case Booked = 'booked';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::AvailableAm => 'Available (AM)',
            self::AvailablePm => 'Available (PM)',
            self::NotAvailable => 'Not Available',
            self::Booked => 'Booked',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::AvailableAm, self::AvailablePm => 'info',
            self::NotAvailable => 'danger',
            self::Booked => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }

    /**
     * Booked is derived from the candidate's actual bookings, never chosen by
     * hand — this is the option list for the day picker everywhere else.
     *
     * @return array<string, string>
     */
    public static function settableOptions(): array
    {
        return collect(self::cases())
            ->reject(fn (self $case): bool => $case === self::Booked)
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
