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

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
