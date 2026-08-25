<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\BookingFilters;
use App\Filament\Support\CandidateSummaryAction;
use App\Filament\Support\ClientSummaryAction;
use App\Models\Booking;
use App\Models\Industry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('candidate.first_name')
                    ->label('Candidate')
                    ->state(function (Booking $record): string {
                        $candidate = $record->candidate()->withTrashed()->first();

                        if (! $candidate) {
                            return 'Unknown candidate';
                        }

                        $name = trim("{$candidate->first_name} {$candidate->last_name}");

                        return $candidate->trashed() ? "{$name} (deleted)" : $name;
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $candidateModelClass = Industry::candidateModelForSlug(active_industry() ?? '');

                        if (! $candidateModelClass) {
                            return $query;
                        }

                        return $query->orWhereHasMorph(
                            'candidate',
                            [$candidateModelClass],
                            fn (Builder $query) => $query
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                        );
                    }),
                TextColumn::make('jobTitle.name')
                    ->label('Job Title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (BookingStatus $state): string => $state->label())
                    ->color(fn (BookingStatus $state): string => $state->color()),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                BookingFilters::client(),
                BookingFilters::candidate(),
                BookingFilters::consultant(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ClientSummaryAction::make(fn (Booking $record) => $record->client),
                CandidateSummaryAction::make(fn (Booking $record) => $record->candidate()->withTrashed()->first()),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
