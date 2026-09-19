<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The company_industry pivot, promoted to a real model so its own
 * uses_bookings/uses_perm flags (App\Filament\Resources\Companies\Schemas\
 * CompanyFeaturesForm) can be edited per row via a Repeater — a plain
 * BelongsToMany's pivotData() only supports one uniform value across every
 * selected record in a single save, not one that differs per industry.
 */
class CompanyIndustry extends Pivot
{
    public $incrementing = true;

    protected $table = 'company_industry';

    protected function casts(): array
    {
        return [
            'uses_bookings' => 'boolean',
            'uses_perm' => 'boolean',
            'complex_booking' => 'boolean',
            'care_logging' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    /**
     * Whether the given company has Bookings switched on for the given
     * industry — the single source of truth every access gate in the app
     * should call through, whether resolved from the staff session cache
     * (see active_industry_uses_bookings()) or directly from a candidate's
     * or client's own company/industry, which have no such cache.
     */
    public static function usesBookings(?int $companyId, ?int $industryId): bool
    {
        return static::feature('uses_bookings', $companyId, $industryId);
    }

    public static function usesPerm(?int $companyId, ?int $industryId): bool
    {
        return static::feature('uses_perm', $companyId, $industryId);
    }

    /**
     * Unlike usesBookings()/usesPerm(), this fails closed (false) — it gates
     * brand-new booking-form complexity (Sleep-In/Waking Night shift types)
     * that must never silently appear anywhere a data gap exists, rather
     * than existing behaviour that should stay on by default.
     */
    public static function usesComplexBooking(?int $companyId, ?int $industryId): bool
    {
        return static::feature('complex_booking', $companyId, $industryId, default: false);
    }

    /**
     * Same fail-closed reasoning as usesComplexBooking() — care logging is a
     * per-shift compliance requirement that only applies once a site admin
     * has deliberately switched it on for a company+industry.
     */
    public static function usesCareLogging(?int $companyId, ?int $industryId): bool
    {
        return static::feature('care_logging', $companyId, $industryId, default: false);
    }

    /**
     * Fails open (true) by default when there's no company/industry to
     * check, or no matching row — a data gap should never silently hide a
     * feature that's meant to be on. Pass default: false for an opt-in flag
     * (see usesComplexBooking()) where a data gap must never silently
     * reveal something nobody enabled.
     */
    private static function feature(string $column, ?int $companyId, ?int $industryId, bool $default = true): bool
    {
        if (! $companyId || ! $industryId) {
            return $default;
        }

        return static::query()
            ->where('company_id', $companyId)
            ->where('industry_id', $industryId)
            ->value($column) ?? $default;
    }
}
