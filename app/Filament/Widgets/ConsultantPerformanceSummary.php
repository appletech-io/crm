<?php

namespace App\Filament\Widgets;

use App\Ai\Agents\PerformanceSummaryAgent;
use App\Filament\Pages\ConsultantMonthlyReport;
use App\Filament\Resources\Bookings\Widgets\BookingWeekStats;
use App\Models\ConsultantKpiTarget;
use App\Models\User;
use App\Services\Reporting\ConsultantPerformanceSummary as PerformanceCalculator;
use App\Services\Reporting\KpiStatus;
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

    /**
     * Null until {@see self::loadSummary()} has run — the blade view uses
     * this to show a loading state while the AI narration is fetched, since
     * generating it can be slow enough that it shouldn't block the rest of
     * the dashboard rendering.
     */
    public ?string $summary = null;

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
        $target = $this->activeKpiTarget();

        $gpStatus = KpiStatus::for($stats['gp'], $target?->gp_target);
        $daysStatus = KpiStatus::for($stats['daysPlaced'], $target?->candidate_days_target);
        $candidatesStatus = KpiStatus::for($stats['candidates'], $target?->working_candidates_target);
        $clientsStatus = KpiStatus::for($stats['clients'], $target?->clients_booked_target);
        $rebookStatus = KpiStatus::for($stats['rebookRate'], $target?->rebook_rate_target);

        return [
            Stat::make('Gross Profit', '£'.number_format($stats['gp'], 2))
                ->description($target?->gp_target !== null ? 'Target: £'.number_format($target->gp_target, 2) : null)
                ->color($gpStatus)
                ->extraAttributes(static::statBackgroundAttributes($gpStatus)),
            Stat::make('Candidate Days Out', $stats['daysPlaced'])
                ->description($target?->candidate_days_target !== null ? "Target: {$target->candidate_days_target}" : null)
                ->color($daysStatus)
                ->extraAttributes(static::statBackgroundAttributes($daysStatus)),
            Stat::make('Working Candidates', $stats['candidates'])
                ->description($target?->working_candidates_target !== null ? "Target: {$target->working_candidates_target}" : null)
                ->color($candidatesStatus)
                ->extraAttributes(static::statBackgroundAttributes($candidatesStatus)),
            Stat::make('Clients Booked', $stats['clients'])
                ->description($target?->clients_booked_target !== null ? "Target: {$target->clients_booked_target}" : null)
                ->color($clientsStatus)
                ->extraAttributes(static::statBackgroundAttributes($clientsStatus)),
            Stat::make('Rebook Rate', $stats['rebookRate'] !== null ? number_format($stats['rebookRate'], 1).'%' : '—')
                ->description($target?->rebook_rate_target !== null
                    ? 'Next week vs this week — Target: '.number_format($target->rebook_rate_target, 1).'%'
                    : 'Next week vs this week')
                ->color($rebookStatus)
                ->extraAttributes(static::statBackgroundAttributes($rebookStatus)),
        ];
    }

    /**
     * Stat::color() only tints the description text (see
     * stats-overview-widget/stat.blade.php), not the card itself — this
     * fills in the rest of the card's background with the same RAG colour,
     * via extraAttributes() on the outer element, using Filament's own
     * success/warning/danger theme colours so it matches dark mode for free.
     *
     * @return array<string, string>
     */
    private static function statBackgroundAttributes(?string $status): array
    {
        $classes = match ($status) {
            'success' => 'bg-success-50 dark:bg-success-500/10',
            'warning' => 'bg-warning-50 dark:bg-warning-500/10',
            'danger' => 'bg-danger-50 dark:bg-danger-500/10',
            default => null,
        };

        return $classes === null ? [] : ['class' => $classes];
    }

    /**
     * Null while viewing "All Consultants" (no single consultant's target
     * makes sense against an aggregate) or when that consultant simply has
     * no target set for the active industry yet — either way, KpiStatus::for()
     * already treats a null target as "no color", so every stat above just
     * falls back to today's plain, uncolored styling.
     */
    private function activeKpiTarget(): ?ConsultantKpiTarget
    {
        $consultantId = $this->activeConsultantId();

        if (! $consultantId) {
            return null;
        }

        return User::find($consultantId)?->kpiTargetFor(active_industry_id());
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
     * them is cached per consultant for 2 hours — regenerating it on every
     * dashboard view would be slow and needlessly expensive, and the
     * underlying numbers don't need to-the-minute freshness in prose form.
     *
     * The cache key must include company and active industry — when
     * $consultantId is null ("All Consultants"), the underlying figures are
     * scoped by Booking::scopeForActiveIndustry() to the viewer's own
     * company/sector, so the cached narration has to be scoped the same way.
     * Without this, one company's "All Consultants" summary could be served
     * to a different company (or a different sector of the same company)
     * viewing the same calendar week.
     */
    public function summaryText(): string
    {
        $consultantId = $this->activeConsultantId();
        $stats = $this->weekStats();
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $companyId = Auth::user()?->company_id;
        $industryId = active_industry_id();

        return Cache::remember(
            "consultant-performance-summary:{$companyId}:{$industryId}:{$consultantId}:{$weekStart}",
            now()->addHours(2),
            fn (): string => $this->generateSummary($consultantId, $stats),
        );
    }

    /**
     * Called from the blade view via wire:init, so the AI narration loads
     * in a separate round trip after the rest of the widget has already
     * rendered, rather than blocking the whole dashboard on it.
     */
    public function loadSummary(): void
    {
        $this->summary = $this->summaryText();
    }

    /**
     * An admin switching consultants makes the previous summary stale — go
     * back to the loading state and fetch the new one, rather than leaving
     * the wrong consultant's narration on screen. This is the one dropdown
     * for the whole dashboard, so other widgets that also filter by
     * consultant (e.g. the calls/meetings KPI widget) are told to follow it.
     */
    public function updatedConsultantId(): void
    {
        $this->summary = null;
        $this->loadSummary();

        $this->dispatch('dashboard-consultant-changed', consultantId: $this->consultantId);
    }

    /**
     * The monthly report is per-consultant. A non-admin always has an
     * implicit "consultant" (themselves), so it's always offered to them;
     * an admin only gets it once they've narrowed the dashboard down to one
     * specific consultant, not while viewing "All Consultants".
     */
    public function showMonthlyReportLink(): bool
    {
        return ! $this->isAdmin() || $this->consultantId !== null;
    }

    public function monthlyReportUrl(): string
    {
        return ConsultantMonthlyReport::getUrl([
            'consultantId' => $this->isAdmin() ? $this->consultantId : Auth::id(),
        ]);
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
