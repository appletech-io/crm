<?php

namespace App\Filament\Resources\Actions\Tables;

use App\Filament\Resources\Actions\Schemas\ActionForm;
use App\Filament\Support\ConditionsRepeaterField;
use App\Models\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ActionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('model_type')
                    ->label('Applies To')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => ActionForm::modelTypeOptions()[$state] ?? $state),

                TextColumn::make('conditions')
                    ->label('Conditions')
                    ->state(function (Action $record): array {
                        $suggestions = ActionForm::suggestionsFor($record->model_type);

                        $labels = collect($record->conditions ?? [])
                            ->map(fn (array $condition): string => ConditionsRepeaterField::conditionLabel($condition, $suggestions))
                            ->values()
                            ->all();

                        if (count($labels) <= 4) {
                            return $labels;
                        }

                        return [...array_slice($labels, 0, 4), '+'.(count($labels) - 4).' more'];
                    })
                    ->badge()
                    ->color(fn (string $state): string => str_starts_with($state, '+') ? 'gray' : 'success'),

                TextColumn::make('todo_name')
                    ->label('Creates To-Do')
                    ->wrap()
                    ->limit(60),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
