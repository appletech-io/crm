<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\JobSettingsOverview;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class JobSettings extends Page
{
    protected string $view = 'filament.pages.job-settings';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Job Settings';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 12;

    public static function canAccess(): bool
    {
        return active_industry() !== null;
    }

    public function getWidgets(): array
    {
        return [
            JobSettingsOverview::class,
        ];
    }
}
