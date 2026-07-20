<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CalendarOverviewWidget;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Calendar extends Page
{
    protected string $view = 'filament.pages.calendar';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Calendar';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        // Impersonation logs the site_admin in as the target company's actual
        // user, so this excludes their own site_admin account without needing
        // to check the impersonation session state directly.
        return ! (auth()->user()?->hasRole('site_admin') ?? false);
    }

    public function getWidgets(): array
    {
        return [
            CalendarOverviewWidget::class,
        ];
    }
}
