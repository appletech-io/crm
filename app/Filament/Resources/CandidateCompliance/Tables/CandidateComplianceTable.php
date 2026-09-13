<?php

namespace App\Filament\Resources\CandidateCompliance\Tables;

use App\Filament\Resources\CandidateCompliance\CandidateComplianceResource;
use App\Models\Candidate;
use App\Models\ComplianceItem;
use App\Services\Candidates\ComplianceRequirements;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CandidateComplianceTable
{
    private const CACHE_STORE = 'array';

    private const CACHE_PREFIX = 'candidate-compliance-table:';

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['jobTitle', 'complianceValues'])
                ->whereIn('id', static::incompleteCandidateIds($query)))
            ->columns([
                TextColumn::make('first_name')
                    ->label('First Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->label('Last Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jobTitle.name')
                    ->label('Job Title')
                    ->placeholder('—'),
                TextColumn::make('compliance')
                    ->label('Compliance')
                    ->badge()
                    ->state(function (Candidate $record): string {
                        $checks = Cache::store(self::CACHE_STORE)->get(self::CACHE_PREFIX."candidate:{$record->id}")
                            ?? ComplianceRequirements::forJobTitle($record, $record->jobTitle);
                        $complete = collect($checks)->filter(fn (array $check): bool => $check['complete'])->count();

                        return "{$complete} / ".count($checks).' complete';
                    })
                    ->color('warning'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Review')
                    ->url(fn (Candidate $record): string => CandidateComplianceResource::getUrl('edit', ['record' => $record])),
            ])
            ->defaultSort('created_at');
    }

    /**
     * A pure to-do list — this table only ever shows candidates who still
     * have at least one outstanding Compliance Item. A candidate with no
     * job title (and therefore no requirements) is trivially complete, so
     * never appears here either.
     *
     * Two distinct N+1s made this page reliably time out with more than a
     * handful of candidates:
     *
     * 1. Naively calling ComplianceRequirements::forJobTitle() per candidate
     *    refetched the same compliance items/fields and the same
     *    candidate's own values once per candidate, even when many
     *    candidates share a job title. Fetching every relevant job title's
     *    items once upfront (see itemsGroupedByJobTitle()) turns that into
     *    a handful of queries regardless of candidate count.
     * 2. Filament invokes modifyQueryUsing()'s closure several times per
     *    single page load (once per internal purpose — counting, fetching,
     *    etc.), so even the fixed version of this method was still running
     *    its full computation repeatedly. Caching the result — keyed on the
     *    incoming query's own SQL/bindings, in the request-scoped 'array'
     *    store — makes repeat invocations with the same effective filters
     *    free. The per-candidate checks are cached alongside it so the
     *    "compliance" column's state() closure (evaluated separately, once
     *    per visible row) doesn't recompute what this method just did.
     *
     * @return array<int, int>
     */
    private static function incompleteCandidateIds(Builder $query): array
    {
        $cacheKey = self::CACHE_PREFIX.'ids:'.md5($query->toSql().serialize($query->getBindings()));

        return Cache::store(self::CACHE_STORE)->rememberForever($cacheKey, function () use ($query): array {
            $candidates = (clone $query)->with(['jobTitle', 'complianceValues'])->get();

            $itemsByJobTitleId = static::itemsGroupedByJobTitle(
                $candidates->pluck('job_title_id')->filter()->unique()->values()
            );

            return $candidates
                ->filter(function (Candidate $candidate) use ($itemsByJobTitleId): bool {
                    $items = $candidate->job_title_id ? ($itemsByJobTitleId->get($candidate->job_title_id) ?? collect()) : collect();
                    $checks = ComplianceRequirements::usingItems($candidate, $items);
                    Cache::store(self::CACHE_STORE)->forever(self::CACHE_PREFIX."candidate:{$candidate->id}", $checks);

                    return ! collect($checks)->every(fn (array $check): bool => $check['complete']);
                })
                ->pluck('id')
                ->all();
        });
    }

    /**
     * Every Compliance Item (with its fields) required by any of the given
     * job titles, fetched in one query and grouped back by job_title_id —
     * the batched equivalent of calling
     * $jobTitle->complianceItems()->with('fields')->get() once per title.
     *
     * @param  Collection<int, int>  $jobTitleIds
     * @return Collection<int, Collection<int, ComplianceItem>>
     */
    private static function itemsGroupedByJobTitle(Collection $jobTitleIds): Collection
    {
        if ($jobTitleIds->isEmpty()) {
            return collect();
        }

        return ComplianceItem::query()
            ->whereHas('jobTitles', fn (Builder $q) => $q->whereIn('job_titles.id', $jobTitleIds))
            ->with(['fields', 'jobTitles' => fn ($q) => $q->whereIn('job_titles.id', $jobTitleIds)])
            ->get()
            ->flatMap(fn (ComplianceItem $item): array => $item->jobTitles
                ->map(fn ($jobTitle): array => ['job_title_id' => $jobTitle->id, 'item' => $item])
                ->all())
            ->groupBy('job_title_id')
            ->map(fn (Collection $rows): Collection => $rows->pluck('item'));
    }
}
