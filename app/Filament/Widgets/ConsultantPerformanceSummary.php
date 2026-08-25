<?php

namespace App\Filament\Widgets;

use App\Ai\Agents\PerformanceSummaryAgent;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Widgets\BookingWeekStats;
use App\Models\User;
use App\Services\Reporting\ConsultantPerformanceSummary as PerformanceCalculator;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * A brief, AI-narrated version of the week's performance figures (the same
 * gross profit / days-out / candidates / clients math as
 * {@see BookingWeekStats}, plus a
 * rebook rate) for the consultant's own dashboard, with a link through to
 * the full Bookings breakdown for detail.
 */
class ConsultantPerformanceSummary extends StatsOverviewWidget
{
    protected string $view = 'filament.widgets.consultant-performance-summary';

    protected static ?int $sort = 0;

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
            Stat::make('Gross Profit', '£'.number_format($stats['gp'], 2)),
            Stat::make('Candidate Days Out', $stats['daysPlaced']),
            Stat::make('Working Candidates', $stats['candidates']),
            Stat::make('Clients Booked', $stats['clients']),
            Stat::make('Rebook Rate', $stats['rebookRate'] !== null ? number_format($stats['rebookRate'], 1).'%' : '—')
                ->description('Next week vs this week'),
        ];
    }

    /** @return int | array<string, ?int> | null */
    protected function getColumns(): int|array|null
    {
        return 5;
    }

    /** @return array{clients: int, candidates: int, gp: float, avgMargin: float, daysPlaced: int, rebookRate: ?float} */
    public function weekStats(): array
    {
        $consultantId = $this->activeConsultantId();
        $weekStart = Carbon::now();

        $stats = PerformanceCalculator::forWeek($consultantId, $weekStart);
        $stats['rebookRate'] = PerformanceCalculator::rebookRate($consultantId, $weekStart);

        return $stats;
    }

    /**
     * The figures are computed fresh every render, but the AI narration of
     * them is cached per consultant per week — regenerating it on every
     * dashboard view would be slow and needlessly expensive, and the
     * underlying numbers don't need to-the-minute freshness in prose form.
     */
    public function summaryText(): string
    {
        $consultantId = $this->activeConsultantId();
        $stats = $this->weekStats();
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();

        return Cache::remember(
            "consultant-performance-summary:{$consultantId}:{$weekStart}",
            now()->addHours(6),
            fn (): string => $this->generateSummary($consultantId, $stats),
        );
    }

    public function moreInfoUrl(): string
    {
        return BookingResource::getUrl('index');
    }

    /** @param  array{clients: int, candidates: int, gp: float, avgMargin: float, daysPlaced: int, rebookRate: ?float}  $stats */
    private function generateSummary(?int $consultantId, array $stats): string
    {
        $consultantName = $consultantId ? User::find($consultantId)?->name : null;

        $rebookLine = $stats['rebookRate'] !== null
            ? number_format($stats['rebookRate'], 1).'%'
            : 'not available — nothing was booked this week to compare against';

        $prompt = 'Consultant: '.($consultantName ?? 'the whole team')."\n".
            'This week — gross profit: £'.number_format($stats['gp'], 2).
            ", candidate days out: {$stats['daysPlaced']}, working candidates: {$stats['candidates']}, ".
            "clients booked: {$stats['clients']}, average margin: ".number_format($stats['avgMargin'] * 100, 1)."%.\n".
            "Rebook rate for next week: {$rebookLine}.\n".
            'Write the briefing.';

        try {
            return (string) (new PerformanceSummaryAgent)->prompt($prompt)->text;
        } catch (Throwable $e) {
            report($e);

            return 'Performance summary is temporarily unavailable.';
        }
    }

    private function activeConsultantId(): ?int
    {
        if ($this->isAdmin()) {
            return $this->consultantId;
        }

        return Auth::id();
    }
}
