<?php

namespace App\Filament\Pages;

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
 * An admin-only drill-down from {@see ConsultantPerformanceSummary}
 * — the same figures over a longer (1 or 3 month) window, a week-by-week
 * trend, the consultant's actual logged activity for the period, and a
 * longer AI-written report tying all of that together.
 */
class ConsultantMonthlyReport extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.consultant-monthly-report';

    protected static ?string $title = 'Consultant Monthly Report';

    /** @var array<int, int> */
    private const MONTH_OPTIONS = [1, 3];

    #[Url]
    public ?int $consultantId = null;

    #[Url]
    public int $months = 1;

    public ?string $summary = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
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
        $this->loadSummary();
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
     * Called from the blade view via wire:init, so the AI report loads in a
     * separate round trip after the rest of the page has already rendered,
     * rather than blocking the whole page on it.
     */
    public function loadSummary(): void
    {
        $this->summary = $this->generateSummary();
    }

    public function moreInfoUrl(): string
    {
        return BookingResource::getUrl('index');
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
}
