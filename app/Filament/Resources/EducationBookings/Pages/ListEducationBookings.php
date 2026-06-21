<?php

namespace App\Filament\Resources\EducationBookings\Pages;

use App\Filament\Resources\EducationBookings\EducationBookingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEducationBookings extends ListRecords
{
    protected static string $resource = EducationBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
