<?php

namespace App\Filament\Resources\Vetting\Pages;

use App\Filament\Resources\Vetting\VettingResource;
use Filament\Resources\Pages\ListRecords;

class ListVetting extends ListRecords
{
    protected static string $resource = VettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
