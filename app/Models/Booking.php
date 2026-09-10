<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\BookingStatus;
use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\HasFieldSuggestions;
use App\Models\Traits\HasProviderExternalId;
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

    use HasFieldSuggestions;
    use HasProviderExternalId;
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
            'candidate_rating' => 'integer',
            'candidate_rated_at' => 'datetime',
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
     * Whether this booking still has a scheduled, non-cancelled day today or
     * later. A long-running booking's overall status can already have moved
     * on to AwaitingApproval/Approved once its earliest days are sent for
     * payroll (see refreshPayrollStatus()), even while later days on the
     * same booking haven't happened yet — those later days still need a
     * confirmation to be resendable, so this is checked independently of
     * status rather than only allowing it while status is Upcoming.
     */
    public function hasUpcomingDayPeriods(): bool
    {
        return $this->dayPeriods()
            ->whereNull('cancelled_at')
            ->whereDate('date', '>=', now())
            ->exists();
    }

    /**
     * Whether this booking's commercial terms are final: the client has
     * approved it, or at least one of its days has been approved or pushed
     * to the payroll provider. From that point the client, candidate, job
     * title, status and rates have been billed against and must not move —
     * only the days that aren't locked yet can still be changed, so a
     * consultant can drop a day the candidate is off sick for.
     */
    public function isSettled(): bool
    {
        return $this->isApproved() || $this->hasLockedDayPeriods();
    }

    /**
     * Whether any of this booking's days have been approved by the client or
     * pushed to the payroll provider.
     */
    public function hasLockedDayPeriods(): bool
    {
        return $this->dayPeriods()->lockedForEditing()->exists();
    }

    /**
     * Whether any day on this booking can still be changed. A booking with
     * none left is entirely read-only; one with some is editable schedule-only.
     */
    public function hasEditableDayPeriods(): bool
    {
        return $this->dayPeriods()->editable()->exists();
    }

    public function isRated(): bool
    {
        return $this->candidate_rated_at !== null;
    }

    /**
     * A client rates the candidate they were booked with, out of 5, once the
     * booking has taken place.
     */
    public function scopeAwaitingCandidateRating(Builder $query): Builder
    {
        return $query->whereNull('candidate_rated_at')
            ->where('start_date', '<=', now())
            ->where('start_date', '>=', now()->subMonth());
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

    /** @return array<string, array{0: class-string<Model>, 1: array<int, string>}> */
    protected static function relationSuggestions(): array
    {
        return [
            'client' => [Client::class, ['company_id', 'industry_id']],
            'jobTitle' => [JobTitle::class, []],
        ];
    }

    /** @return array<int, string> */
    protected static function toManyRelationSuggestions(): array
    {
        return ['dayPeriods'];
    }

    /** @return array<string, array{label: string, type: string, options?: array<string, string>}> */
    protected static function computedFieldSuggestions(): array
    {
        return [
            'status' => [
                'label' => 'Status',
                'type' => 'select',
                'options' => BookingStatus::options(),
            ],
        ];
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

    public function providerErrors(): HasMany
    {
        return $this->hasMany(ProviderError::class);
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_id');
    }

    public function scopeVisibleToCurrentUser(Builder $query): Builder
    {
        $query->forActiveIndustry();

        if (auth()->user()?->isAdmin()) {
            return $query;
        }

        return $query->where('consultant_id', auth()->id());
    }

    /**
     * Restrict bookings to whichever candidate model belongs to the current
     * user's active sector, so a multi-sector company's consultants don't see
     * bookings belonging to a different sector's candidates mixed together.
     */
    public function scopeForActiveIndustry(Builder $query): Builder
    {
        $candidateModel = Industry::candidateModelForSlug(active_industry() ?? '');

        if (! $candidateModel) {
            return $query;
        }

        return $query->where('candidate_type', $candidateModel);
    }

    /**
     * A Requested booking hasn't been accepted yet, so it has no business
     * appearing anywhere a genuinely scheduled booking would — the weekly
     * view, the "All" bookings table, or payroll (its own admin page and the
     * client portal) — until it moves past Requested. Applied everywhere
     * except the dedicated Requests tab.
     */
    public function scopeExcludingRequests(Builder $query): Builder
    {
        return $query->where('status', '!=', BookingStatus::Requested);
    }
}
