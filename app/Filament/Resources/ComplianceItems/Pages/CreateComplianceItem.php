<?php

namespace App\Filament\Resources\ComplianceItems\Pages;

use App\Filament\Resources\ComplianceItems\ComplianceItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateComplianceItem extends CreateRecord
{
    protected static string $resource = ComplianceItemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['industry_id'] = active_industry_id();

        return $data;
    }
}
