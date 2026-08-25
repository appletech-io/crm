<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Enums\EmailTemplateAudience;
use App\Filament\Support\ClientSummaryAction;
use App\Filament\Support\SendCustomEmailAction;
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
use Illuminate\Support\Facades\Auth;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('clientType.name')
                    ->label('Client Type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('postcode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('consultant_id')
                    ->label('Consultant')
                    ->searchable()
                    ->visible(fn (): bool => Auth::user()?->isAdmin() ?? false)
                    ->options(fn (): array => User::role('consultant')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ClientSummaryAction::make(),
                SendCustomEmailAction::record(EmailTemplateAudience::Client),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    SendCustomEmailAction::bulk(EmailTemplateAudience::Client),
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => Auth::user()?->isAdmin() ?? false),
                    ForceDeleteBulkAction::make()
                        ->visible(fn (): bool => Auth::user()?->isAdmin() ?? false),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
