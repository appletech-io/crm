<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleAllClients')
                ->label(fn (): string => session(Client::ADMIN_VIEWING_ALL_CLIENTS_SESSION_KEY, false)
                    ? 'Show My Clients Only'
                    : 'Show All Clients'
                )
                ->icon(fn (): string => session(Client::ADMIN_VIEWING_ALL_CLIENTS_SESSION_KEY, false)
                    ? 'heroicon-o-user'
                    : 'heroicon-o-users'
                )
                ->color('gray')
                ->visible(fn (): bool => Auth::user()?->isAdmin() ?? false)
                ->action(function (): void {
                    session([
                        Client::ADMIN_VIEWING_ALL_CLIENTS_SESSION_KEY => ! session(Client::ADMIN_VIEWING_ALL_CLIENTS_SESSION_KEY, false),
                    ]);

                    $this->resetTable();
                }),
            CreateAction::make()
                ->label('New Client')
                ->modalHeading('Add Client')
                ->createAnother(false)
                ->modalWidth('sm')
                ->schema([
                    TextInput::make('name')
                        ->label('Client Name')
                        ->required()
                        ->maxLength(255),
                ])
                ->mutateFormDataUsing(function (array $data): array {
                    $data['industry_id'] = active_industry_id();
                    $data['consultant_id'] = auth()->id();

                    return $data;
                })
                ->after(function (Client $record) {
                    return redirect($this->getResource()::getUrl('edit', ['record' => $record]));
                }),
        ];
    }
}
