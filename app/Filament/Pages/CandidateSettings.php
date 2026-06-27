<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CandidateSettingsOverview;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class CandidateSettings extends Page
{
    protected string $view = 'filament.pages.candidate-settings';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Candidate Settings';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return active_industry() !== null && auth()->user()?->hasAnyRole(['admin', 'site_admin', 'consultant', 'resourcer']);
    }

    public function getWidgets(): array
    {
        return [
            CandidateSettingsOverview::class,
        ];
    }
}
