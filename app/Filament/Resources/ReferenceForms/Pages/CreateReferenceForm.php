<?php

namespace App\Filament\Resources\ReferenceForms\Pages;

use App\Filament\Resources\ReferenceForms\ReferenceFormResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReferenceForm extends CreateRecord
{
    protected static string $resource = ReferenceFormResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['industry_id'] = active_industry_id();

        return $data;
    }
}
