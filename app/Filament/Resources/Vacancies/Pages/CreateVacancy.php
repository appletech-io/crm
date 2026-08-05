<?php

namespace App\Filament\Resources\Vacancies\Pages;

use App\Filament\Resources\Vacancies\VacancyResource;
use App\Models\Vacancy;
use Filament\Resources\Pages\CreateRecord;

class CreateVacancy extends CreateRecord
{
    protected static string $resource = VacancyResource::class;

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $this->form->fill($this->queryStringPrefillData());

        $this->callHook('afterFill');
    }

    /** @return array<string, mixed>|null */
    protected function queryStringPrefillData(): ?array
    {
        $clientId = request()->query('client_id');

        if (blank($clientId)) {
            return null;
        }

        return [
            'client_id' => $clientId,
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['consultant_id'] = auth()->id();
        $data['slug'] = Vacancy::generateUniqueSlug($data['title']);

        return $data;
    }
}
