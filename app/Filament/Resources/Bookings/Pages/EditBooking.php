<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Actions\Bookings\BookingCreated;
use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Schemas\BookingForm;
use App\Models\Booking;
use App\Models\Industry;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)
            ->disabled(fn (): bool => $this->isApproved());
    }

    protected function getFormActions(): array
    {
        if ($this->isApproved()) {
            return [$this->getCancelFormAction()];
        }

        return parent::getFormActions();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resendConfirmationEmails')
                ->label('Resend Confirmation Emails')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('This will regenerate the booking confirmation PDF and resend the confirmation emails to the candidate and client.')
                ->hidden(fn (): bool => $this->isApproved())
                ->action(function (): void {
                    /** @var Booking $record */
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

    protected function isApproved(): bool
    {
        /** @var Booking $record */
        $record = $this->record;

        return $record->status === BookingStatus::Approved;
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Booking $record */
        $record = $this->record;

        $data['day_periods'] = BookingForm::loadDayPeriods($record);

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['candidate_type'] = Industry::candidateModelForSlug(active_industry() ?? '');

        return $data;
    }

    protected function afterSave(): void
    {
        BookingForm::syncDayPeriods($this->record, $this->form->getRawState()['day_periods'] ?? []);
    }
}
