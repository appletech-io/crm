<?php

namespace App\Filament\Resources\HealthcareVetting\Pages;

use App\Filament\Resources\HealthcareVetting\HealthcareVettingResource;
use Filament\Resources\Pages\ListRecords;

class ListHealthcareVetting extends ListRecords
{
    protected static string $resource = HealthcareVettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
