<?php

namespace App\Filament\Resources\SampleProfiles\Tables;

use App\Models\SampleProfile;
use App\Services\Candidates\Document;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SampleProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('path')
                    ->label('File')
                    ->formatStateUsing(fn (string $state): string => basename($state))
                    ->url(fn (SampleProfile $record): string => Document::viewUrl($record->path))
                    ->openUrlInNewTab(),
                TextColumn::make('uploadedBy.name')
                    ->label('Uploaded By')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
