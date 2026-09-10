<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Enums\EmailTemplateAudience;
use App\Filament\Support\ClientSummaryAction;
use App\Filament\Support\SendCustomEmailAction;
use App\Models\Client;
use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
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
                    static::reassignConsultantAction(),
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => Auth::user()?->isAdmin() ?? false),
                    ForceDeleteBulkAction::make()
                        ->visible(fn (): bool => Auth::user()?->isAdmin() ?? false),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Reassigns the selected clients to a different consultant — updating
     * consultant_id, which Client's own observer then uses to move them
     * between consultants' main client pools (what actually drives
     * visibility). Lets an admin move a whole book of clients at once, e.g.
     * when a consultant leaves: filter by the old consultant via the filter
     * above, select all, reassign to their replacement.
     */
    private static function reassignConsultantAction(): BulkAction
    {
        return BulkAction::make('reassignConsultant')
            ->label('Reassign Consultant')
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (): bool => Auth::user()?->isAdmin() ?? false)
            ->deselectRecordsAfterCompletion()
            ->schema([
                Select::make('consultant_id')
                    ->label('New Consultant')
                    ->searchable()
                    ->required()
                    ->options(fn (): array => User::role('consultant')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                    ),
            ])
            ->action(function (array $data, Collection $records): void {
                $records->each(fn (Client $client) => $client->update(['consultant_id' => $data['consultant_id']]));

                Notification::make()
                    ->title("Reassigned {$records->count()} client(s)")
                    ->success()
                    ->send();
            });
    }
}
