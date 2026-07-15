<?php

namespace App\Filament\Resources\EducationBookings\Pages;

use App\Actions\Bookings\BookingCreated;
use App\Enums\BookingStatus;
use App\Filament\Resources\EducationBookings\EducationBookingResource;
use App\Filament\Resources\EducationBookings\Schemas\EducationBookingForm;
use App\Models\EducationCandidate;
use Filament\Resources\Pages\CreateRecord;

class CreateEducationBooking extends CreateRecord
{
    protected static string $resource = EducationBookingResource::class;

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
        $clientId = request()->query('education_client_id');
        $jobTitleId = request()->query('job_title_id');
        $startDate = request()->query('start_date');

        if (blank($candidateId) && blank($clientId) && blank($jobTitleId) && blank($startDate)) {
            return null;
        }

        return [
            'status' => BookingStatus::Upcoming->value,
            'education_client_id' => $clientId,
            'job_title_id' => $jobTitleId,
            'candidate_id' => $candidateId,
            'start_date' => $startDate,
            'end_date' => $startDate,
            'day_periods' => EducationBookingForm::dayPeriodsForRange($startDate, $startDate),
            ...EducationBookingForm::defaultRates($candidateId, $clientId, $jobTitleId),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['candidate_type'] = EducationCandidate::class;
        $data['consultant_id'] = EducationCandidate::find($data['candidate_id'] ?? null)?->consultant_id;

        return $data;
    }

    protected function afterCreate(): void
    {
        EducationBookingForm::syncDayPeriods($this->record, $this->form->getRawState()['day_periods'] ?? []);

        BookingCreated::run($this->record);
    }
}
