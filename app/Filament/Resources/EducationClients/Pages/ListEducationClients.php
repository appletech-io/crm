<?php

namespace App\Filament\Resources\EducationClients\Pages;

use App\Filament\Resources\EducationClients\EducationClientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEducationClients extends ListRecords
{
    protected static string $resource = EducationClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
