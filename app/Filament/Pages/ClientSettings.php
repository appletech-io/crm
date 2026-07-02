<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ClientSettingsOverview;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ClientSettings extends Page
{
    protected string $view = 'filament.pages.client-settings';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $navigationLabel = 'Client Settings';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 11;

    public static function canAccess(): bool
    {
        return active_industry() !== null && auth()->user()?->hasAnyRole(['admin', 'site_admin']);
    }

    public function getWidgets(): array
    {
        return [
            ClientSettingsOverview::class,
        ];
    }
}
