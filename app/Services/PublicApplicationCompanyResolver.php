<?php

namespace App\Services;

use App\Models\CandidateReference;
use App\Models\Company;
use App\Models\EducationApplication;
use App\Models\HealthcareApplication;
use App\Models\Vacancy;

/**
 * Resolves which company a public page belongs to — used only to pick the
 * right logo for {@see resources/views/layouts/application.blade.php}, since
 * none of the public application/reference/verify/vacancy pages it wraps
 * have an authenticated user to resolve a company from any other way. A
 * component's own public properties (e.g. $application, $reference,
 * $vacancy) can't be used for this instead — Livewire's #[Layout(...)]
 * attribute only accepts compile-time-constant params, so a per-request
 * value never reaches the layout that way. The two resolution paths are
 * kept as separate methods rather than one because they're keyed off
 * differently-named route parameters ({token} vs {vacancy}), so the layout
 * tries each in turn.
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

    /**
     * Accepts mixed rather than ?Vacancy since the caller passes
     * request()->route('vacancy') straight through — resolved to a Vacancy
     * instance by implicit route-model binding on the vacancy apply route,
     * but null (or, in principle, an unresolved raw value) on every other
     * route this shared layout is used from.
     */
    public static function forVacancy(mixed $vacancy): ?Company
    {
        return $vacancy instanceof Vacancy ? $vacancy->company : null;
    }
}
