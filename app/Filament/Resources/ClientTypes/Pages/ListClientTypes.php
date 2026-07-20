<?php

namespace App\Filament\Resources\ClientTypes\Pages;

use App\Filament\Resources\ClientTypes\ClientTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClientTypes extends ListRecords
{
    protected static string $resource = ClientTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
