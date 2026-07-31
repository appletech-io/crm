<?php

namespace App\Filament\Resources\MarketingCampaigns\RelationManagers;

use App\Models\CampaignSend;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SendsRelationManager extends RelationManager
{
    protected static string $relationship = 'sends';

    protected static ?string $title = 'Sends';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('client.name')
                    ->label('Client'),
                TextColumn::make('contact')
                    ->label('Contact')
                    ->getStateUsing(fn (CampaignSend $record): string => $record->contact
                        ? trim("{$record->contact->first_name} {$record->contact->last_name}")
                        : '—'),
                TextColumn::make('subject')
                    ->wrap(),
                TextColumn::make('template.name')
                    ->label('Template')
                    ->placeholder('Ad-hoc'),
                TextColumn::make('sentBy.name')
                    ->label('Sent by')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Sent at')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
