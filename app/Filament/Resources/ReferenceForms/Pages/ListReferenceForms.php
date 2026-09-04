<?php

namespace App\Filament\Resources\ReferenceForms\Pages;

use App\Filament\Resources\ReferenceForms\ReferenceFormResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReferenceForms extends ListRecords
{
    protected static string $resource = ReferenceFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
