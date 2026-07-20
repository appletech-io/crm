<?php

namespace App\Filament\Resources\Bookings\Widgets;

use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\User;
use App\Services\Booking\BookingDayPeriods;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class BookingWeekStats extends StatsOverviewWidget
{
    protected string $view = 'filament.resources.bookings.widgets.booking-week-stats';

    public ?int $consultantId = null;

    public function isAdmin(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    /** @return array<int, string> */
    public function consultantOptions(): array
    {
        return User::role('consultant')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $stats = $this->weekStats();

        return [
            Stat::make('Clients This Week', $stats['clients']),
            Stat::make('Candidates This Week', $stats['candidates']),
            Stat::make('GP This Week', '£'.number_format($stats['gp'], 2)),
            Stat::make('Days Placed This Week', $stats['daysPlaced']),
        ];
    }

    /** @return int | array<string, ?int> | null */
    protected function getColumns(): int|array|null
    {
        return 4;
    }

    /** @return array{clients: int, candidates: int, gp: float, daysPlaced: int} */
    public function weekStats(): array
    {
        $start = now()->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY);

        $dayPeriods = BookingDay::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereNull('cancelled_at')
            ->whereHas('booking', function ($query): void {
                $query->visibleToCurrentUser();

                if ($this->isAdmin() && $this->consultantId) {
                    $query->where('consultant_id', $this->consultantId);
                }
            })
            ->with('booking')
            ->get();

        $bookings = $dayPeriods->pluck('booking')->unique('id');

        $gp = $dayPeriods->groupBy('booking_id')->sum(function ($periods) {
            /** @var Booking $booking */
            $booking = $periods->first()->booking;
            $payRates = BookingDayPeriods::ratesFor($booking, 'pay');
            $chargeRates = BookingDayPeriods::ratesFor($booking, 'charge');

            return $periods->sum(
                fn (BookingDay $period): float => ($chargeRates[$period->period->value] ?? 0) - ($payRates[$period->period->value] ?? 0)
            );
        });

        return [
            'clients' => $bookings->pluck('client_id')->unique()->count(),
            'candidates' => $bookings->map(fn (Booking $booking): string => "{$booking->candidate_type}|{$booking->candidate_id}")->unique()->count(),
            'gp' => round($gp, 2),
            'daysPlaced' => $dayPeriods->count(),
        ];
    }
}
