<?php

namespace App\Filament\Resources\TodoItems\Tables;

use App\Enums\TodoPriority;
use App\Filament\Resources\TodoItems\Schemas\TodoItemForm;
use App\Models\TodoItem;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TodoItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('is_complete')
                    ->label('Done')
                    ->boolean()
                    ->state(fn (TodoItem $record): bool => $record->isComplete()),
                TextColumn::make('name')
                    ->searchable()
                    ->wrap()
                    ->limit(TodoItemForm::NAME_MAX_LENGTH),
                TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn (TodoPriority $state): string => $state->label())
                    ->color(fn (TodoPriority $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('model_type')
                    ->label('Linked To')
                    ->state(fn (TodoItem $record): string => $record->linkedRecordLabel() ?? '—'),
                TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('completed_at')
            ->filters([
                SelectFilter::make('priority')
                    ->options(TodoPriority::options()),
                TernaryFilter::make('completed_at')
                    ->label('Completed')
                    ->nullable()
                    ->trueLabel('Completed')
                    ->falseLabel('Outstanding'),
            ])
            ->recordActions([
                Action::make('toggleComplete')
                    ->label(fn (TodoItem $record): string => $record->isComplete() ? 'Reopen' : 'Complete')
                    ->icon(fn (TodoItem $record): string => $record->isComplete() ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-check')
                    ->color(fn (TodoItem $record): string => $record->isComplete() ? 'gray' : 'success')
                    ->action(fn (TodoItem $record) => $record->update([
                        'completed_at' => $record->isComplete() ? null : now(),
                    ])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
