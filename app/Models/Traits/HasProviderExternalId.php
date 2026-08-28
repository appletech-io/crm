<?php

namespace App\Models\Traits;

use App\Enums\Integration;
use App\Models\ProviderExternalId;
use Closure;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Lets a model (Client, EducationCandidate, HealthcareCandidate, Candidate,
 * PaymentProvider) be referenced under a payroll provider's own ID scheme.
 * An admin can pre-empt this by typing the provider's real pre-existing ID
 * into the record's edit form before this app ever pushes the record —
 * resolveProviderExternalId() always prefers whatever's stored over
 * generating a new one, and never overwrites a stored value once set.
 */
trait HasProviderExternalId
{
    public function providerExternalIds(): MorphMany
    {
        return $this->morphMany(ProviderExternalId::class, 'model');
    }

    public function providerExternalId(Integration $provider): ?string
    {
        return $this->providerExternalIds()
            ->where('provider', $provider->value)
            ->value('external_id');
    }

    /**
     * Returns the stored external ID for this provider, generating and
     * persisting one from $fallback() if none exists yet.
     */
    public function resolveProviderExternalId(Integration $provider, Closure $fallback): string
    {
        $existing = $this->providerExternalId($provider);

        if ($existing !== null) {
            return $existing;
        }

        $generated = $fallback();

        $this->providerExternalIds()->create([
            'provider' => $provider->value,
            'external_id' => $generated,
        ]);

        return $generated;
    }

    /**
     * Direct admin edit of the stored external ID — unlike
     * resolveProviderExternalId() this always overwrites, and clears the
     * row entirely when blanked out. Used by the "Payroll Provider ID"
     * field on this record's edit form.
     */
    public function setProviderExternalId(Integration $provider, ?string $externalId): void
    {
        if (blank($externalId)) {
            $this->providerExternalIds()->where('provider', $provider->value)->delete();

            return;
        }

        $this->providerExternalIds()->updateOrCreate(
            ['provider' => $provider->value],
            ['external_id' => $externalId],
        );
    }
}
