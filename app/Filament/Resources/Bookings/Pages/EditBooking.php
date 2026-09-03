<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Actions\Bookings\BookingCreated;
use App\Enums\BookingStatus;
use App\Enums\Integration;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Schemas\BookingForm;
use App\Jobs\GenerateBookingConfirmationPdf;
use App\Jobs\SendBookingConfirmationEmail;
use App\Jobs\SendClientBookingConfirmationEmail;
use App\Jobs\SendTimesheetToPayrollProvider;
use App\Models\Booking;
use App\Models\ClientContact;
use App\Models\Industry;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

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
            Action::make('retryPayrollSubmission')
                ->label('Retry Payroll Submission')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => $this->hasProviderError())
                ->action(function (): void {
                    /** @var Booking $record */
                    $record = $this->record;

                    try {
                        SendTimesheetToPayrollProvider::dispatchSync($record);
                    } catch (\Throwable) {
                        // recordFailure() inside the job already persisted
                        // the error detail — the check below picks it up.
                    }

                    if ($this->hasProviderError()) {
                        Notification::make()
                            ->danger()
                            ->title('Retry failed — see the error below')
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Payroll submission retried successfully')
                        ->success()
                        ->send();
                }),
            ActionGroup::make([
                Action::make('resendBothConfirmationEmails')
                    ->label('Both')
                    ->visible(fn (): bool => $this->isUpcoming())
                    ->requiresConfirmation()
                    ->modalDescription('This will regenerate the booking confirmation PDF and resend the confirmation emails to the candidate and client.')
                    ->action(function (): void {
                        /** @var Booking $record */
                        $record = $this->record;

                        BookingCreated::run($record);

                        Notification::make()
                            ->title('Confirmation emails queued for resend')
                            ->success()
                            ->send();
                    }),
                Action::make('resendClientConfirmationEmail')
                    ->label('Client Only')
                    ->visible(fn (): bool => $this->isUpcoming())
                    ->requiresConfirmation()
                    ->modalDescription('This will regenerate the booking confirmation PDF and resend the confirmation email to the client only.')
                    ->action(function (): void {
                        /** @var Booking $record */
                        $record = $this->record;

                        GenerateBookingConfirmationPdf::dispatch($record);

                        $record->client?->bookingContacts()->each(
                            fn (ClientContact $contact) => SendClientBookingConfirmationEmail::dispatch($record, $contact)
                        );

                        Notification::make()
                            ->title('Client confirmation email queued for resend')
                            ->success()
                            ->send();
                    }),
                Action::make('resendCandidateConfirmationEmail')
                    ->label('Candidate Only')
                    ->visible(fn (): bool => $this->isUpcoming())
                    ->requiresConfirmation()
                    ->modalDescription('This will regenerate the booking confirmation PDF and resend the confirmation email to the candidate only.')
                    ->action(function (): void {
                        /** @var Booking $record */
                        $record = $this->record;

                        GenerateBookingConfirmationPdf::dispatch($record);
                        SendBookingConfirmationEmail::dispatch($record);

                        Notification::make()
                            ->title('Candidate confirmation email queued for resend')
                            ->success()
                            ->send();
                    }),
            ])
                ->label('Resend Confirmation Emails')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->button()
                ->visible(fn (): bool => $this->isUpcoming()),
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

    protected function isUpcoming(): bool
    {
        /** @var Booking $record */
        $record = $this->record;

        return $record->status === BookingStatus::Upcoming;
    }

    protected function hasProviderError(): bool
    {
        /** @var Booking $record */
        $record = $this->record;

        $provider = $record->company->payroll_provider;

        if (! $provider instanceof Integration) {
            return false;
        }

        return $record->providerErrors()->where('provider', $provider->value)->exists();
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

        // Only admins can see the Payroll Provider ID field (see the form's
        // matching isAdmin() gate) — this mirrors it so a consultant saving
        // the rest of the form, where the field was never rendered, can't
        // wipe out an existing external ID mapping via its absent state.
        if (! (Auth::user()?->isAdmin() ?? false)) {
            return;
        }

        $provider = Auth::user()->company->payroll_provider;

        if ($provider) {
            $this->record->setProviderExternalId($provider, $this->form->getRawState()['payroll_provider_id'] ?? null);
        }
    }
}
