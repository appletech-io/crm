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
            Action::make('confirmBooking')
                ->label('Confirm Booking')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('This confirms the booking, generates the confirmation PDF, and emails the candidate and client.')
                ->visible(fn (): bool => $this->isRequested())
                ->action(function (): void {
                    /** @var Booking $record */
                    $record = $this->record;

                    if (! $record->job_title_id) {
                        Notification::make()
                            ->danger()
                            ->title('Set a job title and pay/charge rates before confirming.')
                            ->send();

                        return;
                    }

                    $record->update(['status' => BookingStatus::Upcoming]);

                    BookingCreated::run($record);

                    Notification::make()
                        ->title('Booking confirmed — confirmation emails queued.')
                        ->success()
                        ->send();
                }),
            Action::make('rejectBooking')
                ->label('Reject Request')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('This deletes the booking request. The client is not notified automatically.')
                ->visible(fn (): bool => $this->isRequested())
                ->action(function (): void {
                    /** @var Booking $record */
                    $record = $this->record;

                    $record->delete();

                    Notification::make()
                        ->title('Booking request rejected')
                        ->success()
                        ->send();

                    $this->redirect(BookingResource::getUrl('index'));
                }),
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

    protected function isRequested(): bool
    {
        /** @var Booking $record */
        $record = $this->record;

        return $record->status === BookingStatus::Requested;
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Booking $record */
        $record = $this->record;

        $dayPeriods = BookingForm::loadDayPeriods($record);

        // A client-submitted request has no BookingDay rows yet (those are
        // only created on save), so without this the schedule would open
        // empty and the consultant would have to re-touch a date field just
        // to trigger the same default-generation the client's dates already
        // imply.
        if (empty($dayPeriods) && $record->status === BookingStatus::Requested) {
            $dayPeriods = BookingForm::dayPeriodsForRange($record->start_date, $record->end_date);
        }

        $data['day_periods'] = $dayPeriods;

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
