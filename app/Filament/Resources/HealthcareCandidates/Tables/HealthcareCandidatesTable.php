<?php

namespace App\Filament\Resources\HealthcareCandidates\Tables;

use App\Enums\EmailTemplateAudience;
use App\Filament\Support\SendCustomEmailAction;
use App\Models\CandidateSkill;
use App\Models\CandidateStatus;
use App\Models\HealthcareCandidate;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class HealthcareCandidatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('statuses.status'))
            ->columns([
                TextColumn::make('first_name')
                    ->label('First Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->label('Last Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('candidate_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (HealthcareCandidate $record): array|string => $record->statuses->isNotEmpty()
                        ? $record->statuses->pluck('status.name')->filter()->values()->toArray()
                        : 'No Status'
                    )
                    ->color(function (HealthcareCandidate $record, string $state): string {
                        if ($state === 'No Status') {
                            return 'gray';
                        }

                        return $record->statuses->first(fn ($s) => $s->status->name === $state)?->status->color ?? 'gray';
                    }),
                TextColumn::make('average_rating')
                    ->label('Rating')
                    ->badge()
                    ->formatStateUsing(fn (?float $state, HealthcareCandidate $record): string => $state !== null
                        ? number_format($state, 1)." ★ ({$record->ratings_count})"
                        : 'Not yet rated')
                    ->color(fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 4 => 'success',
                        $state >= 3 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options(fn (): array => CandidateStatus::query()
                        ->where('company_id', Auth::user()->company_id)
                        ->where('industry_id', active_industry_id())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                    )
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['values'],
                        fn ($q, $values) => $q->whereHas('statuses', fn ($q) => $q->whereIn('candidate_status_id', $values))
                    )),
                SelectFilter::make('skills')
                    ->label('Skill')
                    ->multiple()
                    ->options(fn (): array => CandidateSkill::query()
                        ->where('company_id', Auth::user()->company_id)
                        ->where('industry_id', active_industry_id())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                    )
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['values'],
                        fn ($q, $values) => $q->whereHas('skills', fn ($q) => $q->whereIn('candidate_skill_candidates.candidate_skill_id', $values))
                    )),
                SelectFilter::make('consultant_id')
                    ->label('Consultant')
                    ->searchable()
                    ->visible(fn (): bool => Auth::user()?->isAdmin() ?? false)
                    ->options(fn (): array => User::role('consultant')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                    ),
                SelectFilter::make('average_rating')
                    ->label('Rating')
                    ->options([
                        '4' => '4+ stars',
                        '3' => '3+ stars',
                        '2' => '2+ stars',
                        '1' => '1+ stars',
                        'unrated' => 'Not yet rated',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'unrated' => $query->whereNull('average_rating'),
                        '1', '2', '3', '4' => $query->where('average_rating', '>=', (float) $data['value']),
                        default => $query,
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                SendCustomEmailAction::record(EmailTemplateAudience::Candidate),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    SendCustomEmailAction::bulk(EmailTemplateAudience::Candidate),
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => Auth::user()?->isAdmin() ?? false),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
