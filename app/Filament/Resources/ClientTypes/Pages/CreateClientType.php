<?php

namespace App\Filament\Resources\ClientTypes\Pages;

use App\Filament\Resources\ClientTypes\ClientTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClientType extends CreateRecord
{
    protected static string $resource = ClientTypeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['industry_id'] = active_industry_id();

        return $data;
    }
}
