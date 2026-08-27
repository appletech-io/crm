<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ComplianceSettingsOverview;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ComplianceSettings extends Page
{
    protected string $view = 'filament.pages.compliance-settings';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Compliance Settings';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 13;

    public static function canAccess(): bool
    {
        return active_industry() !== null && (auth()->user()?->hasRole('admin') ?? false);
    }

    public function getWidgets(): array
    {
        return [
            ComplianceSettingsOverview::class,
        ];
    }
}
