<?php

namespace App\Filament\Resources\EducationClients\Pages;

use App\Filament\Resources\EducationClients\EducationClientResource;
use App\Models\EducationClient;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListEducationClients extends ListRecords
{
    protected static string $resource = EducationClientResource::class;

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
                ->after(function (EducationClient $record) {
                    return redirect($this->getResource()::getUrl('edit', ['record' => $record]));
                }),
        ];
    }
}
