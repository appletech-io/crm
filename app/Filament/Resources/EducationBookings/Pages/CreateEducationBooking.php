<?php

namespace App\Filament\Resources\EducationBookings\Pages;

use App\Filament\Resources\EducationBookings\EducationBookingResource;
use App\Filament\Resources\EducationBookings\Schemas\EducationBookingForm;
use Filament\Resources\Pages\CreateRecord;

class CreateEducationBooking extends CreateRecord
{
    protected static string $resource = EducationBookingResource::class;

    protected function afterCreate(): void
    {
        EducationBookingForm::syncDayPeriods($this->record, $this->form->getRawState()['day_periods'] ?? []);
    }
}
