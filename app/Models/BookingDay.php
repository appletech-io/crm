<?php

namespace App\Models;

use App\Enums\BookingDayPeriod;
use App\Enums\PayrollStatus;
use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * A day the consultant may no longer change: the client has approved it,
     * or it has already been pushed to the payroll provider. Either way the
     * money behind it is committed, so the booking form leaves these days
     * exactly as they are while still allowing the rest of the schedule to
     * be edited — see BookingForm::syncDayPeriods().
     *
     * Days merely awaiting the client's confirmation, and disputed days, stay
     * editable on purpose: fixing a day the client disputed is precisely how
     * a dispute gets resolved.
     */
    public function isLockedForEditing(): bool
    {
        return $this->isApproved() || $this->sent_to_provider_at !== null;
    }

    /** The query-side counterpart of isLockedForEditing(). */
    public function scopeLockedForEditing(Builder $query): Builder
    {
        return $query->where(fn (Builder $query): Builder => $query
            ->whereNotNull('approved_at')
            ->orWhereNotNull('sent_to_provider_at'));
    }

    public function scopeEditable(Builder $query): Builder
    {
        return $query->whereNull('approved_at')->whereNull('sent_to_provider_at');
    }

    public function payrollStatus(): PayrollStatus
    {
        return match (true) {
            $this->isDisputed() => PayrollStatus::Disputed,
            $this->isApproved() => PayrollStatus::Approved,
            $this->isPayrollConfirmationSent() => PayrollStatus::Sent,
            default => PayrollStatus::Pending,
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
