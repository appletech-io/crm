<?php

namespace App\Filament\Resources\EducationBookings\Pages;

use App\Filament\Resources\EducationBookings\EducationBookingResource;
use App\Filament\Resources\EducationBookings\Schemas\EducationBookingForm;
use App\Models\EducationBooking;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditEducationBooking extends EditRecord
{
    protected static string $resource = EducationBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var EducationBooking $record */
        $record = $this->record;

        $data['day_periods'] = EducationBookingForm::loadDayPeriods($record);

        return $data;
    }

    protected function afterSave(): void
    {
        EducationBookingForm::syncDayPeriods($this->record, $this->form->getRawState()['day_periods'] ?? []);
    }
}
