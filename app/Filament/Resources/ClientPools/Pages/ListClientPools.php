<?php

namespace App\Filament\Resources\ClientPools\Pages;

use App\Filament\Resources\ClientPools\ClientPoolResource;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListClientPools extends ListRecords
{
    protected static string $resource = ClientPoolResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New pool')
                ->modalHeading('Add pool')
                ->createAnother(false)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                ])
                ->mutateDataUsing(function (array $data): array {
                    $data['industry_id'] = active_industry_id();
                    $data['user_id'] = Auth::id();

                    return $data;
                }),
        ];
    }
}
