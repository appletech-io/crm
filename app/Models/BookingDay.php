<?php

namespace App\Models;

use App\Enums\BookingDayPeriod;
use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingDay extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'period' => BookingDayPeriod::class,
            'cancelled_at' => 'datetime',
            'payroll_confirmation_sent_at' => 'datetime',
            'approved_at' => 'datetime',
            'disputed_at' => 'datetime',
            'sent_to_provider_at' => 'datetime',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isPayrollConfirmationSent(): bool
    {
        return $this->payroll_confirmation_sent_at !== null;
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function isDisputed(): bool
    {
        return $this->disputed_at !== null;
    }

    public function payrollStatus(): string
    {
        return match (true) {
            $this->isDisputed() => 'disputed',
            $this->isApproved() => 'approved',
            $this->isPayrollConfirmationSent() => 'sent',
            default => 'pending',
        };
    }

    /**
     * The client-facing charge rate that applies to this specific day,
     * drawn from whichever of the booking's rate fields matches its period —
     * an hourly rate for an Hours day, the half-day rate for an Am/Pm day,
     * or the full day rate otherwise.
     */
    public function chargeRate(): ?float
    {
        return match ($this->period) {
            BookingDayPeriod::Hours => $this->booking->hourly_charge_rate,
            BookingDayPeriod::Am, BookingDayPeriod::Pm => $this->booking->half_day_charge_rate,
            BookingDayPeriod::FullDay => $this->booking->day_charge_rate,
        };
    }

    /**
     * The candidate-facing pay rate for this specific day — the same
     * period-based lookup as chargeRate(), just against the booking's pay
     * rate fields instead of its charge rate fields.
     */
    public function payRate(): ?float
    {
        return match ($this->period) {
            BookingDayPeriod::Hours => $this->booking->hourly_rate,
            BookingDayPeriod::Am, BookingDayPeriod::Pm => $this->booking->half_day_rate,
            BookingDayPeriod::FullDay => $this->booking->day_rate,
        };
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
