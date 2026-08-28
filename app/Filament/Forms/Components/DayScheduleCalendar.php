<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

class DayScheduleCalendar extends Field
{
    protected string $view = 'filament.forms.components.day-schedule-calendar';

    protected bool|Closure $hoursEnabled = true;

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
}
