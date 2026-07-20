<?php

namespace App\Filament\Concerns;

use App\Models\Company;
use App\Services\Booking\TimesheetPeriod;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Carbon;

trait HasTimesheetPeriodNavigation
{
    public string $periodStart;

    abstract protected function periodCompany(): Company;

    public function goToPreviousPeriod(): void
    {
        $this->periodStart = TimesheetPeriod::previous($this->periodCompany(), Carbon::parse($this->periodStart))['start']->toDateString();
    }

    public function goToNextPeriod(): void
    {
        $this->periodStart = TimesheetPeriod::next($this->periodCompany(), Carbon::parse($this->periodStart))['start']->toDateString();
    }

    public function goToCurrentPeriod(): void
    {
        $this->periodStart = TimesheetPeriod::current($this->periodCompany())['start']->toDateString();
    }

    /** @return array{start: Carbon, end: Carbon} */
    protected function currentPeriod(): array
    {
        return TimesheetPeriod::containing($this->periodCompany(), Carbon::parse($this->periodStart));
    }

    /** @return array<int, string> */
    protected function disabledCalendarDates(): array
    {
        $windowStart = Carbon::now()->subMonths(6)->startOfDay();
        $windowEnd = Carbon::now()->addMonths(6)->endOfDay();

        $valid = collect(TimesheetPeriod::selectableDatesBetween($this->periodCompany(), $windowStart, $windowEnd));

        return collect(CarbonPeriod::create($windowStart, $windowEnd))
            ->map(fn (Carbon $date): string => $date->toDateString())
            ->reject(fn (string $date): bool => $valid->contains($date))
            ->values()
            ->all();
    }

    /** @return array<int, Action> */
    protected function periodNavigationActions(): array
    {
        return [
            Action::make('previousPeriod')
                ->label('')
                ->icon('heroicon-o-chevron-left')
                ->action(fn () => $this->goToPreviousPeriod()),
            Action::make('currentPeriod')
                ->label('Current Period')
                ->action(fn () => $this->goToCurrentPeriod()),
            Action::make('nextPeriod')
                ->label('')
                ->icon('heroicon-o-chevron-right')
                ->action(fn () => $this->goToNextPeriod()),
            Action::make('jumpToPeriod')
                ->label('Jump to Period')
                ->icon('heroicon-o-calendar-days')
                ->color('gray')
                ->schema([
                    DatePicker::make('date')
                        ->label('Select a period')
                        ->native(false)
                        ->required()
                        ->disabledDates(fn (): array => $this->disabledCalendarDates()),
                ])
                ->action(function (array $data): void {
                    $this->periodStart = TimesheetPeriod::containing($this->periodCompany(), Carbon::parse($data['date']))['start']->toDateString();
                }),
        ];
    }
}
