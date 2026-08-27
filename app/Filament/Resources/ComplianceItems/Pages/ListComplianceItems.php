<?php

namespace App\Filament\Resources\ComplianceItems\Pages;

use App\Filament\Resources\ComplianceItems\ComplianceItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListComplianceItems extends ListRecords
{
    protected static string $resource = ComplianceItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
