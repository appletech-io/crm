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
        return true;
    }

    public function getWidgets(): array
    {
        return [
            CalendarOverviewWidget::class,
        ];
    }
}
