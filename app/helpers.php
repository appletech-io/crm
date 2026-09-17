<?php

use App\Models\CompanyIndustry;

if (! function_exists('active_industry')) {
    function active_industry(): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        return Cache::get("user.{$user->id}.active_industry");
    }
}

if (! function_exists('active_industry_id')) {
    function active_industry_id(): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        return Cache::get("user.{$user->id}.active_industry_id");
    }
}

if (! function_exists('active_industry_uses_bookings')) {
    /**
     * Whether the current user's active industry, for their own company,
     * has Bookings switched on — set per company_industry row by a site
     * admin (see App\Filament\Resources\Companies\Schemas\CompanyFeaturesForm),
     * for agencies whose sector is permanent/contract only. Defaults to true
     * (fails open) when there's no user, no active industry, or no
     * matching row, so a data gap never silently hides Bookings/Payroll for
     * an industry that's meant to have them.
     *
     * Only valid for a staff (admin panel) login — active_industry_id() is
     * backed by a session cache that candidate/client portal logins never
     * populate. Those callers should use CompanyIndustry::usesBookings()
     * directly with their own company_id/industry_id instead.
     */
    function active_industry_uses_bookings(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return true;
        }

        return CompanyIndustry::usesBookings($user->company_id, active_industry_id());
    }
}

if (! function_exists('active_industry_uses_perm')) {
    /**
     * The Perm equivalent of active_industry_uses_bookings() — gates Job
     * Pipeline, Jobs (Vacancies), and Vacancy/Placement reporting for
     * agencies whose sector is temp/day-booking only.
     */
    function active_industry_uses_perm(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return true;
        }

        return CompanyIndustry::usesPerm($user->company_id, active_industry_id());
    }
}
