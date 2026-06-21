<?php

namespace App\Filament\Resources\EducationClients\Pages;

use App\Filament\Resources\EducationClients\EducationClientResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditEducationClient extends EditRecord
{
    protected static string $resource = EducationClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
