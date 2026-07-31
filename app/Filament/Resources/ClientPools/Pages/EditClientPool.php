<?php

namespace App\Filament\Resources\ClientPools\Pages;

use App\Filament\Resources\ClientPools\ClientPoolResource;
use App\Filament\Resources\ClientPools\RelationManagers\ClientsRelationManager;
use App\Filament\Support\SendCustomEmailAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClientPool extends EditRecord
{
    protected static string $resource = ClientPoolResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendCustomEmailAction::pool($this->record),
            DeleteAction::make(),
        ];
    }

    public function getRelationManagers(): array
    {
        return [
            ClientsRelationManager::class,
        ];
    }
}
