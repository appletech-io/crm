<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EducationConsultantLeaderboard extends Widget
{
    protected string $view = 'filament.widgets.education-consultant-leaderboard';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public string $selectedMonth = '';

    public function mount(): void
    {
        $this->selectedMonth = Carbon::now()->format('Y-m');
    }

    /**
     * The current month, the 11 before it, and 3 ahead of it — so a
     * consultant's already-booked days for upcoming weeks can be checked in
     * advance, not just reported on after the fact.
     *
     * @return array<string, string>
     */
    public function monthOptions(): array
    {
        return collect(range(-3, 11))
            ->mapWithKeys(function (int $i): array {
                $date = Carbon::now()->startOfMonth()->subMonths($i);

                return [$date->format('Y-m') => $date->format('F Y')];
            })
            ->all();
    }

    /**
     * All complete Monday-Sunday weeks that overlap the selected month. A week is
     * never split at the month boundary, so the first/last week may dip into the
     * neighbouring month.
     *
     * @return Collection<int, Carbon>
     */
    public function weeks(): Collection
    {
        $monthStart = Carbon::createFromFormat('Y-m-d', $this->selectedMonth.'-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $weeks = collect();
        $cursor = $monthStart->copy()->startOfWeek(Carbon::MONDAY);

        while ($cursor->lte($monthEnd)) {
            $weeks->push($cursor->copy());
            $cursor = $cursor->copy()->addWeek();
        }

        return $weeks;
    }

    public function isCurrentWeek(Carbon $weekStart): bool
    {
        return $weekStart->isSameDay(Carbon::now()->startOfWeek(Carbon::MONDAY));
    }

    /** @return Collection<int, array{consultant: User, weeks: Collection<string, array{start: int, current: int, nextWeek: int}>, rankValue: int}> */
    public function leaderboard(): Collection
    {
        $weeks = $this->weeks();

        if ($weeks->isEmpty()) {
            return collect();
        }

        $referenceWeek = $weeks->first(fn (Carbon $week): bool => $this->isCurrentWeek($week)) ?? $weeks->last();

        $consultants = User::role('consultant')
            ->orderBy('name')
            ->get();

        $bookings = Booking::query()
            ->forActiveIndustry()
            ->whereIn('consultant_id', $consultants->pluck('id'))
            ->with(['dayPeriods' => fn ($query) => $query->whereNull('cancelled_at')])
            ->get(['id', 'consultant_id', 'created_at']);

        return $consultants
            ->map(function (User $consultant) use ($weeks, $bookings, $referenceWeek): array {
                // A single booking can span many days across many weeks, so
                // every metric here counts booking DAYS, not bookings — each
                // day carries its own parent booking's created_at, since
                // that's what determines whether that specific day was
                // booked in advance of the week it falls in.
                $days = $bookings->where('consultant_id', $consultant->id)
                    ->flatMap(fn (Booking $booking): Collection => $booking->dayPeriods
                        ->map(fn (BookingDay $dayPeriod): array => [
                            'date' => $dayPeriod->date,
                            'bookedAt' => $booking->created_at,
                        ]));

                $weekData = $weeks->mapWithKeys(function (Carbon $weekStart) use ($days): array {
                    $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
                    $nextWeekStart = $weekStart->copy()->addWeek();
                    $nextWeekEnd = $nextWeekStart->copy()->endOfWeek(Carbon::SUNDAY);

                    $thisWeekDays = $days->filter(fn (array $day): bool => $day['date']->betweenIncluded($weekStart, $weekEnd));

                    $current = $thisWeekDays->count();

                    $start = $thisWeekDays
                        ->filter(fn (array $day): bool => $day['bookedAt']->lt($weekStart))
                        ->count();

                    $nextWeek = $days
                        ->filter(fn (array $day): bool => $day['date']->betweenIncluded($nextWeekStart, $nextWeekEnd))
                        ->count();

                    return [$weekStart->toDateString() => [
                        'start' => $start,
                        'current' => $current,
                        'nextWeek' => $nextWeek,
                    ]];
                });

                return [
                    'consultant' => $consultant,
                    'weeks' => $weekData,
                    'rankValue' => $weekData->get($referenceWeek->toDateString())['current'] ?? 0,
                ];
            })
            ->sortByDesc('rankValue')
            ->values();
    }
}
