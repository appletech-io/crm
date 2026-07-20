<?php

namespace App\Filament\Resources\ClientTypes\Pages;

use App\Filament\Resources\ClientTypes\ClientTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClientType extends EditRecord
{
    protected static string $resource = ClientTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
