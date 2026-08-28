<?php

namespace App\Filament\Concerns;

use App\Enums\Integration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Shared "Payroll Submission Failed" alert + retry behaviour, used on Client
 * and Candidate (Education/Healthcare/generic) edit pages — the same
 * pattern BookingForm/EditBooking already established for a booking's own
 * provider errors, just against whichever model's providerErrors() relation
 * is passed in (a plain hasMany for Client, a morphMany for the candidates).
 */
trait HasPayrollProviderErrorAlert
{
    /** @return Collection<int, string> */
    protected static function currentProviderErrors(Model $record): Collection
    {
        $provider = $record->company->payroll_provider;

        if (! $provider instanceof Integration) {
            return collect();
        }

        return collect($record->providerErrors()->where('provider', $provider->value)->value('errors') ?? []);
    }

    protected function hasProviderError(Model $record): bool
    {
        $provider = $record->company->payroll_provider;

        if (! $provider instanceof Integration) {
            return false;
        }

        return $record->providerErrors()->where('provider', $provider->value)->exists();
    }
}
