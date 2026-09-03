<?php

namespace App\Filament\Widgets;

use App\Enums\BookingDayPeriod;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Services\Booking\TimesheetPeriod;
use Filament\Actions\Action;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BookingTimesheetOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    public ?Booking $record = null;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Sent Timesheets')
            ->description('One row per billing period (set on the company) already sent to the payroll provider.')
            ->records(fn (): array => static::periodRows($this->record))
            ->columns([
                TextColumn::make('period_label')
                    ->label('Period'),
                TextColumn::make('days_count')
                    ->label('Days'),
                TextColumn::make('total_pay_label')
                    ->label('Total Pay'),
                TextColumn::make('sent_at_label')
                    ->label('Sent to Payroll'),
            ])
            ->recordActions([
                Action::make('viewBreakdown')
                    ->label('View breakdown')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading(fn (array $record): string => $record['period_label'])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->schema(fn (array $record): array => [
                        View::make('filament.forms.components.timesheet-period-breakdown')
                            ->viewData(['days' => $record['days']]),
                    ]),
            ])
            ->paginated(false)
            ->emptyStateHeading('No timesheets sent yet.');
    }

    /**
     * Groups this booking's approved-and-sent days into one row per billing
     * period — using the company's own TimesheetPeriod (weekly/biweekly/
     * monthly, whichever it's configured for), the same boundary
     * SendTimesheetToPayrollProvider itself groups by when actually
     * submitting — rather than a hardcoded calendar week.
     *
     * @return array<int, array{id: string, period_label: string, days_count: int, total_pay_label: string, sent_at_label: string, days: array<int, array<string, string>>}>
     */
    public static function periodRows(Booking $booking): array
    {
        $days = BookingDay::query()
            ->where('booking_id', $booking->id)
            ->whereNotNull('approved_at')
            ->whereNotNull('sent_to_provider_at')
            ->orderBy('date')
            ->get();

        if ($days->isEmpty()) {
            return [];
        }

        $company = $booking->company;

        return $days
            ->groupBy(fn (BookingDay $day): string => TimesheetPeriod::containing($company, $day->date)['end']->toDateString())
            ->map(function (Collection $periodDays, string $periodEndKey) use ($company, $booking): array {
                $period = TimesheetPeriod::containing($company, $periodDays->first()->date);

                return [
                    'id' => $periodEndKey,
                    'period_label' => $period['start']->format('j M Y').' - '.$period['end']->format('j M Y'),
                    'days_count' => $periodDays->count(),
                    'total_pay_label' => '£'.number_format($periodDays->sum(fn (BookingDay $day): float => static::dayPay($booking, $day)), 2),
                    'sent_at_label' => optional($periodDays->max('sent_to_provider_at'))->format('j M Y, g:ia') ?? '—',
                    'days' => $periodDays->map(fn (BookingDay $day): array => [
                        'date' => $day->date->format('D j M Y'),
                        'period' => $day->period->label(),
                        'pay' => '£'.number_format(static::dayPay($booking, $day), 2),
                        'approved_at' => optional($day->approved_at)->format('j M Y, g:ia') ?? '—',
                        'approved_by' => $day->approvedBy?->name ?? '—',
                        'sent_at' => optional($day->sent_to_provider_at)->format('j M Y, g:ia') ?? '—',
                    ])->values()->all(),
                ];
            })
            ->sortByDesc('id')
            ->values()
            ->all();
    }

    public static function dayPay(Booking $booking, BookingDay $day): float
    {
        return match ($day->period) {
            BookingDayPeriod::FullDay => (float) ($booking->day_rate ?? 0),
            BookingDayPeriod::Am, BookingDayPeriod::Pm => (float) ($booking->half_day_rate ?? 0),
            BookingDayPeriod::Hours => (float) ($booking->hourly_rate ?? 0) * static::hoursFor($day),
        };
    }

    private static function hoursFor(BookingDay $day): float
    {
        if (! $day->time_from || ! $day->time_to) {
            return 0.0;
        }

        return round(abs(Carbon::parse($day->time_from)->diffInMinutes(Carbon::parse($day->time_to))) / 60, 2);
    }
}
