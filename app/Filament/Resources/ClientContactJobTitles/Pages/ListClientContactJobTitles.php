<?php

namespace App\Filament\Resources\ClientContactJobTitles\Pages;

use App\Filament\Resources\ClientContactJobTitles\ClientContactJobTitleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClientContactJobTitles extends ListRecords
{
    protected static string $resource = ClientContactJobTitleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
