<?php

namespace App\Enums;

use App\Models\BookingDay;
use Illuminate\Database\Eloquent\Builder;

/**
 * The slices of a payroll period a consultant can narrow the Payroll tab
 * down to. Not the same set as {@see PayrollStatus}: AwaitingApproval spans
 * three of those statuses (pending, sent and disputed), because what a
 * consultant chases is "everything the client hasn't signed off", not any
 * one status. Absence of a selection means the whole period.
 */
enum PayrollStatusFilter: string
{
    case AwaitingApproval = 'awaiting';
    case Approved = 'approved';
    case Disputed = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingApproval => 'Awaiting approval',
            self::Approved => 'Approved',
            self::Disputed => 'Disputed',
        };
    }

    /**
     * What to say when a period has no days matching this slice — otherwise
     * a fully-approved week reads as a week with no bookings at all.
     */
    public function emptyStateHeading(): string
    {
        return match ($this) {
            self::AwaitingApproval => 'Nothing left to approve for this period',
            self::Approved => 'Nothing approved yet for this period',
            self::Disputed => 'Nothing disputed for this period',
        };
    }

    /**
     * Applied against booking days. A disputed day stays in
     * AwaitingApproval even when an approval timestamp sits alongside the
     * dispute, matching the Disputed-beats-Approved precedence in
     * {@see PayrollStatus}.
     *
     * @param  Builder<BookingDay>  $query
     * @return Builder<BookingDay>
     */
    public function apply(Builder $query): Builder
    {
        return match ($this) {
            self::AwaitingApproval => $query->where(
                fn (Builder $query) => $query->whereNull('approved_at')->orWhereNotNull('disputed_at'),
            ),
            self::Approved => $query->whereNotNull('approved_at')->whereNull('disputed_at'),
            self::Disputed => $query->whereNotNull('disputed_at'),
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
