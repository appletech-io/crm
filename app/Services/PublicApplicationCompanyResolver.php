<?php

namespace App\Services;

use App\Models\CandidateReference;
use App\Models\Company;
use App\Models\EducationApplication;
use App\Models\HealthcareApplication;

/**
 * Resolves which company a public, token-identified application or
 * reference page belongs to — used only to pick the right logo for
 * {@see resources/views/layouts/application.blade.php}, since none of the
 * public application/reference/verify pages it wraps have an authenticated
 * user to resolve a company from any other way. A component's own public
 * properties (e.g. $application, $reference) can't be used for this instead
 * — Livewire's #[Layout(...)] attribute only accepts compile-time-constant
 * params, so a per-request value never reaches the layout that way.
 */
class PublicApplicationCompanyResolver
{
    public static function forToken(?string $token): ?Company
    {
        if (blank($token)) {
            return null;
        }

        return EducationApplication::where('token', $token)->first()?->educationCandidate?->company
            ?? HealthcareApplication::where('token', $token)->first()?->candidate?->company
            ?? CandidateReference::where('token', $token)->first()?->candidate?->company;
    }
}
