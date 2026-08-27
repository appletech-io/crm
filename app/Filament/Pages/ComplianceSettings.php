<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ComplianceSettingsOverview;
use App\Models\Candidate;
use App\Models\Industry;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ComplianceSettings extends Page
{
    protected string $view = 'filament.pages.compliance-settings';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Compliance Settings';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 13;

    /**
     * Compliance Items are the configurable-requirements system for a
     * generic Candidate only — Education/Healthcare candidates' vetting
     * requirements are fixed model columns instead (CandidateVettingRequirements
     * / HealthcareVettingRequirements), so this page has nothing to
     * configure for those industries.
     */
    public static function canAccess(): bool
    {
        return Industry::candidateModelForSlug(active_industry() ?? '') === Candidate::class
            && (auth()->user()?->hasRole('admin') ?? false);
    }

    public function getWidgets(): array
    {
        return [
            ComplianceSettingsOverview::class,
        ];
    }
}
