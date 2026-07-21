<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
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

                    return $data;
                })
                ->after(function (Client $record) {
                    return redirect($this->getResource()::getUrl('edit', ['record' => $record]));
                }),
        ];
    }
}
