<?php

namespace App\Filament\Resources\ComplianceItems\Pages;

use App\Filament\Resources\ComplianceItems\ComplianceItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditComplianceItem extends EditRecord
{
    protected static string $resource = ComplianceItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
