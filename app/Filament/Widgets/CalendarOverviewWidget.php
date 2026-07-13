<?php

namespace App\Filament\Widgets;

use Filament\Actions\Action;
use Guava\Calendar\Enums\CalendarViewType;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\DateClickInfo;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Support\Collection;

class CalendarOverviewWidget extends CalendarWidget
{
    protected CalendarViewType $calendarView = CalendarViewType::DayGridMonth;

    protected bool $dateClickEnabled = true;

    protected bool $dateSelectEnabled = true;

    protected function getEvents(FetchInfo $fetchInfo): Collection|array
    {
        return [];
    }

    protected function onDateClick(DateClickInfo $info): void
    {
        $this->setOption('date', $info->date->toIso8601String());
        $this->setOption('view', CalendarViewType::TimeGridDay->value);
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('backToMonth')
                ->label('Back to Month')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->action(fn () => $this->setOption('view', CalendarViewType::DayGridMonth->value)),
        ];
    }
}
