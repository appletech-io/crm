<?php

namespace App\Filament\Resources\Vacancies\Tables;

use App\Enums\VacancyEmploymentType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class VacanciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Vacancy')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jobTitle.name')
                    ->label('Job Title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jobStatus.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record): string => $record->jobStatus?->color ?? 'gray'),
                TextColumn::make('employment_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (VacancyEmploymentType $state): string => $state->label())
                    ->color(fn (VacancyEmploymentType $state): string => $state === VacancyEmploymentType::Temp ? 'warning' : 'gray'),
                TextColumn::make('salary_min')
                    ->label('Salary')
                    ->money('GBP')
                    ->placeholder('—'),
                TextColumn::make('salary_max')
                    ->label('to')
                    ->money('GBP')
                    ->placeholder('—'),
                TextColumn::make('positions_available')
                    ->label('Positions')
                    ->sortable(),
                TextColumn::make('consultant.name')
                    ->label('Consultant')
                    ->placeholder('—'),
                IconColumn::make('open_for_applications')
                    ->label('Accepts Applications')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('employment_type')
                    ->label('Type')
                    ->options(VacancyEmploymentType::options()),
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
