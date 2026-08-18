<?php

namespace App\Filament\Resources\ClientContactJobTitles\Pages;

use App\Filament\Resources\ClientContactJobTitles\ClientContactJobTitleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClientContactJobTitle extends CreateRecord
{
    protected static string $resource = ClientContactJobTitleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['industry_id'] = active_industry_id();

        return $data;
    }
}
