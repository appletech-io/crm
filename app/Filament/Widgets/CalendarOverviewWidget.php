<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\BookingDay;
use Filament\Actions\Action;
use Guava\Calendar\Enums\CalendarViewType;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\CalendarEvent;
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
        return BookingDay::query()
            ->whereHas('booking', fn ($query) => $query->visibleToCurrentUser())
            ->whereBetween('date', [$fetchInfo->start->toDateString(), $fetchInfo->end->toDateString()])
            ->with(['booking.client', 'booking.candidate', 'booking.jobTitle'])
            ->get()
            ->map(fn (BookingDay $dayPeriod): CalendarEvent => $this->toCalendarEvent($dayPeriod));
    }

    private function toCalendarEvent(BookingDay $dayPeriod): CalendarEvent
    {
        $booking = $dayPeriod->booking;

        $candidateName = trim(collect([$booking?->candidate?->first_name, $booking?->candidate?->last_name])->filter()->implode(' '));
        $clientName = $booking?->client?->name ?? 'Unknown client';

        return CalendarEvent::make($dayPeriod)
            ->title(trim("{$candidateName} @ {$clientName}", ' @'))
            ->start($dayPeriod->date->copy()->startOfDay())
            ->end($dayPeriod->date->copy()->endOfDay())
            ->allDay()
            ->backgroundColor($this->eventColor($dayPeriod))
            ->url($booking ? BookingResource::getUrl('edit', ['record' => $booking]) : '');
    }

    private function eventColor(BookingDay $dayPeriod): string
    {
        return match (true) {
            $dayPeriod->isCancelled() => '#9ca3af',
            $dayPeriod->isDisputed() => '#ef4444',
            $dayPeriod->isApproved() => '#22c55e',
            default => '#16a34a',
        };
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
