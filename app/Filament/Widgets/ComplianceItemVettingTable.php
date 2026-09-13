<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Candidates\CandidateResource;
use App\Models\Candidate;
use App\Models\ComplianceItem;
use App\Services\Candidates\ComplianceRequirements;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;

/**
 * The generic-candidate equivalent of {@see ComplianceVettingTable} — but a
 * generic candidate has no fixed compliance_step/total-steps number to
 * bucket by (their required items are configurable Compliance Items, see
 * ComplianceRequirements, and can vary in count company to company). Each
 * bucket is instead a range of "proportion of Compliance Items complete",
 * computed per candidate rather than read off a column.
 */
class ComplianceItemVettingTable extends TableWidget
{
    private const CACHE_STORE = 'array';

    private const CACHE_PREFIX = 'compliance-item-vetting-table:';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 2;

    public float $ratioFrom = 0.0;

    public float $ratioTo = 1.0;

    public string $bucketHeading = '';

    /**
     * Matches the colours used by {@see ComplianceVettingTable}'s own
     * buckets, for a consistent look across sectors.
     */
    public string $bucketColor = 'gray';

    /** @return array<int, array{from: float, to: float, heading: string, color: string}> */
    public static function buckets(): array
    {
        return [
            ['from' => 0.0, 'to' => 1 / 3, 'heading' => 'Not Complete', 'color' => 'danger'],
            ['from' => 1 / 3, 'to' => 2 / 3, 'heading' => 'Mostly Complete', 'color' => 'warning'],
            ['from' => 2 / 3, 'to' => 1.0, 'heading' => 'Almost Complete', 'color' => 'info'],
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->bucketQuery())
            ->heading($this->coloredHeading())
            ->columns([
                TextColumn::make('first_name')
                    ->label('Name')
                    ->getStateUsing(fn (Candidate $record): string => "{$record->first_name} {$record->last_name}")
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->url(fn (Candidate $record): string => $this->candidateUrl($record)),
                TextColumn::make('compliance_progress')
                    ->label('Compliance')
                    ->badge()
                    ->color($this->bucketColor)
                    ->getStateUsing(fn (Candidate $record): string => $this->progressLabel($record)),
            ])
            ->defaultSort('created_at')
            ->paginated([5, 10, 25]);
    }

    protected function coloredHeading(): Htmlable
    {
        return new HtmlString(Blade::render(
            '<x-filament::badge :color="$color">{{ $label }}</x-filament::badge>',
            ['color' => $this->bucketColor, 'label' => "{$this->bucketHeading} ({$this->bucketQuery()->count()})"],
        ));
    }

    protected function bucketQuery(): Builder
    {
        return Candidate::query()->whereIn('id', $this->candidateIdsInBucket());
    }

    public function progressLabel(Candidate $candidate): string
    {
        $summary = $this->summaryByCandidateId()->get($candidate->id);

        return ($summary['complete'] ?? 0).' / '.($summary['total'] ?? 0).' complete';
    }

    public function candidateUrl(Candidate $candidate): string
    {
        return CandidateResource::getUrl('edit', ['record' => $candidate]);
    }

    /**
     * Every candidate still in Vetting, bucketed by what fraction of their
     * Compliance Items are complete. A candidate with no required items at
     * all counts as ratio 1.0 (nothing outstanding) rather than 0 — the same
     * "trivially complete" treatment ComplianceRequirements::isCompleteForJobTitle()
     * gives an empty checklist.
     *
     * Cached per company/industry in the request-scoped 'array' store —
     * Filament re-invokes the table query closure several times per page
     * load (see CandidateComplianceTable, which hit the same N+1 first),
     * and every bucket widget on the dashboard needs the same candidate set.
     *
     * @return array<int, int>
     */
    private function candidateIdsInBucket(): array
    {
        return $this->summaryByCandidateId()
            ->filter(fn (array $summary): bool => $this->isInBucket($summary['ratio']))
            ->keys()
            ->all();
    }

    private function isInBucket(float $ratio): bool
    {
        if ($ratio < $this->ratioFrom) {
            return false;
        }

        return $this->ratioTo >= 1.0 ? $ratio <= $this->ratioTo : $ratio < $this->ratioTo;
    }

    /**
     * @return Collection<int, array{ratio: float, complete: int, total: int}> keyed by candidate id
     */
    private function summaryByCandidateId(): Collection
    {
        $companyId = Auth::user()?->company_id;
        $industryId = active_industry_id();

        return Cache::store(self::CACHE_STORE)->rememberForever(
            self::CACHE_PREFIX."summary:{$companyId}:{$industryId}",
            function () use ($companyId, $industryId): Collection {
                $candidates = Candidate::query()
                    ->where('industry_id', $industryId)
                    ->whereHas('statuses.status', fn (Builder $query) => $query->where('name', 'Vetting'))
                    ->whereNull('compliance_completed_at')
                    ->with('complianceValues')
                    ->get();

                if ($candidates->isEmpty()) {
                    return collect();
                }

                $items = ComplianceItem::query()
                    ->where('company_id', $companyId)
                    ->where('industry_id', $industryId)
                    ->with('fields')
                    ->get();

                return $candidates->mapWithKeys(function (Candidate $candidate) use ($items): array {
                    $checks = ComplianceRequirements::usingItems($candidate, $items);
                    $total = count($checks);
                    $complete = collect($checks)->filter(fn (array $check): bool => $check['complete'])->count();

                    return [$candidate->id => [
                        'ratio' => $total === 0 ? 1.0 : ($complete / $total),
                        'complete' => $complete,
                        'total' => $total,
                    ]];
                });
            },
        );
    }
}
