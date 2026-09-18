<?php

namespace App\Filament\Resources\UserQuickLinks\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserQuickLinksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                IconColumn::make('icon')
                    ->label(''),

                TextColumn::make('label')
                    ->searchable(),

                TextColumn::make('url')
                    ->label('Link')
                    ->limit(50)
                    ->color('gray'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
