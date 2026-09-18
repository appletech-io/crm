<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

class DayScheduleCalendar extends Field
{
    protected string $view = 'filament.forms.components.day-schedule-calendar';

    protected bool|Closure $hoursEnabled = true;

    protected bool|Closure $complexBookingEnabled = false;

    /**
     * The client-facing booking-request modal has no "Hours" concept
     * (BookingDayPeriod::Hours is already excluded from its period options)
     * — this hides the "Set Hours" bulk action and the hours time inputs
     * for that context, while leaving the consultant-facing Booking form
     * (which does support hourly days) unaffected by default.
     */
    public function hoursEnabled(bool|Closure $condition = true): static
    {
        $this->hoursEnabled = $condition;

        return $this;
    }

    public function isHoursEnabled(): bool
    {
        return (bool) $this->evaluate($this->hoursEnabled);
    }

    /**
     * Shows the Sleep-In/Waking Night bulk-apply buttons and legend —
     * gated behind the company_industry.complex_booking flag (see
     * active_industry_uses_complex_booking()), off by default like the
     * flag itself, so this stays invisible until a site admin turns it on
     * for a specific company+industry.
     */
    public function complexBookingEnabled(bool|Closure $condition = true): static
    {
        $this->complexBookingEnabled = $condition;

        return $this;
    }

    public function isComplexBookingEnabled(): bool
    {
        return (bool) $this->evaluate($this->complexBookingEnabled);
    }
}
