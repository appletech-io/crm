<?php

namespace App\Filament\Resources\ReferenceForms\Pages;

use App\Filament\Resources\ReferenceForms\ReferenceFormResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReferenceForm extends EditRecord
{
    protected static string $resource = ReferenceFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
