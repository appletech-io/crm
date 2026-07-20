<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\BookingStatus;
use App\Models\Traits\BelongsToCompany;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => BookingStatus::class,
            'hourly_rate' => Money::class,
            'day_rate' => Money::class,
            'half_day_rate' => Money::class,
            'hourly_charge_rate' => Money::class,
            'day_charge_rate' => Money::class,
            'half_day_charge_rate' => Money::class,
            'disputed_at' => 'datetime',
        ];
    }

    public function isApproved(): bool
    {
        return $this->status === BookingStatus::Approved;
    }

    public function isDisputed(): bool
    {
        return $this->disputed_at !== null;
    }

    /**
     * Recompute the booking's overall approval/dispute state from its day periods
     * that have been sent for payroll confirmation.
     */
    public function refreshPayrollStatus(): void
    {
        $sentDays = $this->dayPeriods()->whereNotNull('payroll_confirmation_sent_at')->get();

        if ($sentDays->isEmpty()) {
            return;
        }

        $latestDispute = $sentDays->filter(fn (BookingDay $day): bool => $day->isDisputed())
            ->sortByDesc('disputed_at')
            ->first();

        $allApproved = $sentDays->every(fn (BookingDay $day): bool => $day->isApproved());

        $status = $allApproved && ! $latestDispute ? BookingStatus::Approved : BookingStatus::AwaitingApproval;

        $this->update([
            'status' => $this->status === BookingStatus::Completed ? $this->status : $status,
            'disputed_at' => $latestDispute?->disputed_at,
            'dispute_reason' => $latestDispute?->dispute_reason,
        ]);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function candidate(): MorphTo
    {
        return $this->morphTo();
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function dayPeriods(): HasMany
    {
        return $this->hasMany(BookingDay::class)->orderBy('date');
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_id');
    }

    public function scopeVisibleToCurrentUser(Builder $query): Builder
    {
        if (auth()->user()?->isAdmin()) {
            return $query;
        }

        return $query->where('consultant_id', auth()->id());
    }
}
