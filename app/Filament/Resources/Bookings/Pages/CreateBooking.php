<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Actions\Bookings\BookingCreated;
use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Schemas\BookingForm;
use App\Models\Booking;
use App\Models\Industry;
use Filament\Resources\Pages\CreateRecord;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $this->form->fill($this->queryStringPrefillData());

        $this->callHook('afterFill');
    }

    /** @return array<string, mixed>|null */
    protected function queryStringPrefillData(): ?array
    {
        $candidateId = request()->query('candidate_id');
        $clientId = request()->query('client_id');
        $jobTitleId = request()->query('job_title_id');
        $startDate = request()->query('start_date');
        $sourceBookingId = request()->query('source_booking_id');

        if (blank($candidateId) && blank($clientId) && blank($jobTitleId) && blank($startDate)) {
            return null;
        }

        $sourceBooking = filled($sourceBookingId)
            ? Booking::query()->visibleToCurrentUser()->find($sourceBookingId)
            : null;

        return [
            'status' => BookingStatus::Upcoming->value,
            'client_id' => $clientId,
            'job_title_id' => $jobTitleId,
            'candidate_id' => $candidateId,
            'start_date' => $startDate,
            'end_date' => $startDate,
            'day_periods' => BookingForm::dayPeriodsForRange($startDate, $startDate),
            ...($sourceBooking
                ? BookingForm::ratesFromBooking($sourceBooking)
                : BookingForm::defaultRates($candidateId, $clientId, $jobTitleId)),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $candidateModelClass = Industry::candidateModelForSlug(active_industry() ?? '');

        $data['candidate_type'] = $candidateModelClass;
        $data['consultant_id'] = $candidateModelClass ? $candidateModelClass::find($data['candidate_id'] ?? null)?->consultant_id : null;

        return $data;
    }

    protected function afterCreate(): void
    {
        BookingForm::syncDayPeriods($this->record, $this->form->getRawState()['day_periods'] ?? []);

        BookingCreated::run($this->record);
    }
}
