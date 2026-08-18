<?php

namespace App\Filament\Resources\ClientContactJobTitles\Pages;

use App\Filament\Resources\ClientContactJobTitles\ClientContactJobTitleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClientContactJobTitle extends EditRecord
{
    protected static string $resource = ClientContactJobTitleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
