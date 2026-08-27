<?php

namespace App\Filament\Resources\CandidateCompliance\Tables;

use App\Filament\Resources\CandidateCompliance\CandidateComplianceResource;
use App\Models\Candidate;
use App\Services\Candidates\ComplianceRequirements;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CandidateComplianceTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['jobTitle'])
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
                        $checks = ComplianceRequirements::forJobTitle($record, $record->jobTitle);
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
     * @return array<int, int>
     */
    private static function incompleteCandidateIds(Builder $query): array
    {
        return (clone $query)
            ->with('jobTitle')
            ->get()
            ->reject(fn (Candidate $candidate): bool => ComplianceRequirements::isCompleteForJobTitle($candidate, $candidate->jobTitle))
            ->pluck('id')
            ->all();
    }
}
