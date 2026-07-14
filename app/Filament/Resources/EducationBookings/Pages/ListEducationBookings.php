<?php

namespace App\Filament\Resources\EducationBookings\Pages;

use App\Filament\Resources\EducationBookings\EducationBookingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEducationBookings extends ListRecords
{
    protected static string $resource = EducationBookingResource::class;

    protected string $view = 'filament.resources.education-bookings.pages.list-education-bookings';

    public string $activeSection = 'weekly';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
