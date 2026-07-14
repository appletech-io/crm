<?php

namespace App\Filament\Resources\EducationBookings\Tables;

use App\Models\EducationBooking;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class EducationBookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('education_client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('education_candidate.first_name')
                    ->label('Candidate')
                    ->state(function (EducationBooking $record): string {
                        $candidate = $record->education_candidate()->withTrashed()->first();

                        if (! $candidate) {
                            return 'Unknown candidate';
                        }

                        $name = trim("{$candidate->first_name} {$candidate->last_name}");

                        return $candidate->trashed() ? "{$name} (deleted)" : $name;
                    })
                    ->searchable(['education_candidate.first_name', 'education_candidate.last_name'])
                    ->sortable(),
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
                    ->color(fn (string $state): string => match ($state) {
                        'provisional' => 'gray',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        'completed' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
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
