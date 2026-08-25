<?php

namespace App\Filament\Pages;

use App\Ai\Agents\BdmCallCoachingAgent;
use App\Ai\Agents\ConsultantMonthlyReportAgent;
use App\Enums\ActivityType;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Widgets\ConsultantPerformanceSummary;
use App\Models\CandidateActivity;
use App\Models\ClientActivity;
use App\Models\User;
use App\Services\Reporting\ConsultantPerformanceSummary as PerformanceCalculator;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Throwable;

/**
 * A drill-down from {@see ConsultantPerformanceSummary} — the same figures
 * over a longer (1 or 3 month) window, a week-by-week trend, the
 * consultant's actual logged activity for the period, and a longer
 * AI-written report tying all of that together. Admins can view any
 * consultant; a consultant can only ever view their own — {@see self::mount()}
 * forces that regardless of what's in the URL.
 */
class ConsultantMonthlyReport extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.consultant-monthly-report';

    protected static ?string $title = 'Consultant Monthly Report';

    /** @var array<int, int> */
    private const MONTH_OPTIONS = [1, 3];

    /** @var array<int, ActivityType> */
    private const BDM_CALL_TYPES = [ActivityType::BdmCall, ActivityType::Call];

    #[Url]
    public ?int $consultantId = null;

    #[Url]
    public int $months = 1;

    public string $activeTab = 'summary';

    public ?string $summary = null;

    public ?string $bdmSummary = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() || auth()->user()?->hasRole('consultant');
    }

    public function mount(): void
    {
        // A consultant can only ever see their own report — the query
        // string is not a trustworthy source for whose data to show, so
        // this overrides whatever's there rather than merely defaulting it.
        if (! auth()->user()?->isAdmin()) {
            $this->consultantId = auth()->id();
        }

        abort_unless(in_array($this->months, self::MONTH_OPTIONS, true), 404);
        abort_unless($this->consultant() !== null, 404);
    }

    public function getTitle(): string
    {
        return "Monthly Report — {$this->consultant()?->name}";
    }

    public function consultant(): ?User
    {
        if (! $this->consultantId) {
            return null;
        }

        return User::role('consultant')->find($this->consultantId);
    }

    public function setMonths(int $months): void
    {
        $this->months = in_array($months, self::MONTH_OPTIONS, true) ? $months : 1;
        $this->summary = null;
        $this->bdmSummary = null;
        $this->loadAll();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['summary', 'bdm-calls'], true) ? $tab : 'summary';
    }

    public function periodStart(): Carbon
    {
        return Carbon::now()->subMonths($this->months)->startOfDay();
    }

    public function periodEnd(): Carbon
    {
        return Carbon::now()->endOfDay();
    }

    /** @return array{clients: int, candidates: int, gp: float, avgMargin: float, daysPlaced: int} */
    public function periodStats(): array
    {
        return PerformanceCalculator::forRange($this->consultantId, $this->periodStart(), $this->periodEnd());
    }

    /** @return Collection<int, array{weekStart: string, clients: int, candidates: int, gp: float, avgMargin: float, daysPlaced: int}> */
    public function weeklyBreakdown(): Collection
    {
        return PerformanceCalculator::weeklyBreakdown($this->consultantId, $this->periodStart(), $this->periodEnd());
    }

    /** @return Collection<int, array{type: ActivityType, note: ?string, created_at: Carbon, subject: string, kind: string}> */
    public function activities(): Collection
    {
        $start = $this->periodStart();
        $end = $this->periodEnd();

        $candidateActivities = CandidateActivity::query()
            ->where('user_id', $this->consultantId)
            ->whereBetween('created_at', [$start, $end])
            ->with('model')
            ->get()
            ->map(fn (CandidateActivity $activity): array => [
                'type' => $activity->type,
                'note' => $activity->note,
                'created_at' => $activity->created_at,
                'subject' => $activity->model ? trim("{$activity->model->first_name} {$activity->model->last_name}") : 'Unknown candidate',
                'kind' => 'Candidate',
            ]);

        $clientActivities = ClientActivity::query()
            ->where('user_id', $this->consultantId)
            ->whereBetween('created_at', [$start, $end])
            ->with('model')
            ->get()
            ->map(fn (ClientActivity $activity): array => [
                'type' => $activity->type,
                'note' => $activity->note,
                'created_at' => $activity->created_at,
                'subject' => $activity->model?->name ?? 'Unknown client',
                'kind' => 'Client',
            ]);

        return $candidateActivities->concat($clientActivities)
            ->sortByDesc('created_at')
            ->values();
    }

    /**
     * BDM calls and calls to clients only — the activities the BDM Call
     * Coaching tab's AI feedback is based on. Deliberately client
     * activities only, not candidate calls, since this is about business
     * development technique.
     *
     * @return Collection<int, array{note: ?string, created_at: Carbon, subject: string}>
     */
    public function bdmCallActivities(): Collection
    {
        return ClientActivity::query()
            ->where('user_id', $this->consultantId)
            ->whereBetween('created_at', [$this->periodStart(), $this->periodEnd()])
            ->whereIn('type', array_map(fn (ActivityType $type): string => $type->value, self::BDM_CALL_TYPES))
            ->with('model')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ClientActivity $activity): array => [
                'type' => $activity->type,
                'note' => $activity->note,
                'created_at' => $activity->created_at,
                'subject' => $activity->model?->name ?? 'Unknown client',
            ]);
    }

    /** @return array<string, int> */
    public function activityCountsByType(): array
    {
        return $this->activities()
            ->groupBy(fn (array $activity): string => $activity['type']->label())
            ->map->count()
            ->sortDesc()
            ->all();
    }

    /**
     * Called from the blade view via wire:init, so both AI summaries load
     * in a separate round trip after the rest of the page has already
     * rendered — regardless of which tab is active — so switching tabs
     * never has to wait on a fresh AI call.
     */
    public function loadAll(): void
    {
        $this->loadSummary();
        $this->loadBdmSummary();
    }

    public function loadSummary(): void
    {
        $this->summary = $this->generateSummary();
    }

    public function moreInfoUrl(): string
    {
        return BookingResource::getUrl('index');
    }

    /**
     * Called from the blade view via wire:init, so the BDM call coaching
     * loads in a separate round trip rather than blocking the page.
     */
    public function loadBdmSummary(): void
    {
        $this->bdmSummary = $this->generateBdmSummary();
    }

    private function generateSummary(): string
    {
        $consultantId = $this->consultantId;
        $consultantName = $this->consultant()?->name ?? 'Unknown consultant';
        $months = $this->months;
        $stats = $this->periodStats();
        $weeks = $this->weeklyBreakdown();
        $activityCounts = $this->activityCountsByType();

        $weekAnchor = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();

        return Cache::remember(
            "consultant-monthly-report:{$consultantId}:{$months}:{$weekAnchor}",
            now()->addHours(2),
            fn (): string => $this->promptAgent($consultantName, $months, $stats, $weeks, $activityCounts),
        );
    }

    /**
     * @param  array{clients: int, candidates: int, gp: float, avgMargin: float, daysPlaced: int}  $stats
     * @param  Collection<int, array{weekStart: string, clients: int, candidates: int, gp: float, avgMargin: float, daysPlaced: int}>  $weeks
     * @param  array<string, int>  $activityCounts
     */
    private function promptAgent(string $consultantName, int $months, array $stats, Collection $weeks, array $activityCounts): string
    {
        $weekLines = $weeks
            ->map(fn (array $week): string => "{$week['weekStart']}: {$week['daysPlaced']} days out, £".number_format($week['gp'], 2).' GP, '."{$week['clients']} clients")
            ->implode("\n");

        $activityLine = $activityCounts === []
            ? 'No activity logged in this period.'
            : collect($activityCounts)->map(fn (int $count, string $label): string => "{$count} {$label}")->implode(', ');

        $prompt = "Consultant: {$consultantName}\n".
            "Period: last {$months} month(s).\n".
            'Totals — gross profit: £'.number_format($stats['gp'], 2).
            ", candidate days out: {$stats['daysPlaced']}, working candidates: {$stats['candidates']}, ".
            "clients booked: {$stats['clients']}, average margin: ".number_format($stats['avgMargin'] * 100, 1)."%.\n".
            "Week-by-week:\n{$weekLines}\n".
            "Activity logged: {$activityLine}.\n".
            'Write the report.';

        try {
            return (string) (new ConsultantMonthlyReportAgent)->prompt($prompt)->text;
        } catch (Throwable $e) {
            report($e);

            return 'Performance report is temporarily unavailable.';
        }
    }

    private function generateBdmSummary(): string
    {
        $consultantId = $this->consultantId;
        $consultantName = $this->consultant()?->name ?? 'Unknown consultant';
        $months = $this->months;
        $calls = $this->bdmCallActivities();

        $weekAnchor = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();

        return Cache::remember(
            "consultant-bdm-call-coaching:{$consultantId}:{$months}:{$weekAnchor}",
            now()->addHours(2),
            fn (): string => $this->promptBdmAgent($consultantName, $months, $calls),
        );
    }

    /** @param  Collection<int, array{type: ActivityType, note: ?string, created_at: Carbon, subject: string}>  $calls */
    private function promptBdmAgent(string $consultantName, int $months, Collection $calls): string
    {
        if ($calls->isEmpty()) {
            return "No BDM calls or client calls were logged for {$consultantName} in this period, so there's nothing to coach on yet.";
        }

        $callLines = $calls
            ->map(fn (array $call): string => "{$call['created_at']->toDateString()} — {$call['type']->label()} to {$call['subject']}: ".
                (filled($call['note']) ? $call['note'] : '(no note recorded)'))
            ->implode("\n");

        $prompt = "Consultant: {$consultantName}\n".
            "Period: last {$months} month(s).\n".
            "Total BDM/client calls logged: {$calls->count()}.\n".
            "Call log (date — type to client: note):\n{$callLines}\n".
            'Write the coaching feedback.';

        try {
            return (string) (new BdmCallCoachingAgent)->prompt($prompt)->text;
        } catch (Throwable $e) {
            report($e);

            return 'BDM call coaching is temporarily unavailable.';
        }
    }
}
