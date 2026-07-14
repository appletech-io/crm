<?php

namespace App\Filament\Resources\EducationBookings\Pages;

use App\Actions\Bookings\BookingCreated;
use App\Filament\Resources\EducationBookings\EducationBookingResource;
use App\Filament\Resources\EducationBookings\Schemas\EducationBookingForm;
use App\Models\EducationBooking;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEducationBooking extends EditRecord
{
    protected static string $resource = EducationBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resendConfirmationEmails')
                ->label('Resend Confirmation Emails')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('This will regenerate the booking confirmation PDF and resend the confirmation emails to the candidate and client.')
                ->action(function (): void {
                    /** @var EducationBooking $record */
                    $record = $this->record;

                    BookingCreated::run($record);

                    Notification::make()
                        ->title('Confirmation emails queued for resend')
                        ->success()
                        ->send();
                }),
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
