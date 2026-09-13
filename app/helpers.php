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
     * admin (see App\Filament\Resources\Companies\Schemas\CompanyForm), for
     * agencies whose sector is permanent/contract only. Defaults to true
     * (fails open) when there's no user, no active industry, or no
     * matching row, so a data gap never silently hides Bookings/Payroll for
     * an industry that's meant to have them.
     */
    function active_industry_uses_bookings(): bool
    {
        $user = auth()->user();

        if (! $user || ! active_industry_id()) {
            return true;
        }

        return CompanyIndustry::query()
            ->where('company_id', $user->company_id)
            ->where('industry_id', active_industry_id())
            ->value('uses_bookings') ?? true;
    }
}
