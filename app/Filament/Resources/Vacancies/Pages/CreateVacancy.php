<?php

namespace App\Filament\Resources\Vacancies\Pages;

use App\Filament\Resources\Vacancies\VacancyResource;
use App\Jobs\MatchCandidatesToVacancy;
use App\Models\Vacancy;
use Filament\Notifications\Notification;
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

    protected function afterCreate(): void
    {
        // run_match is dehydrated(false) so it never reaches $data/the
        // Eloquent create() call — getRawState() reads the submitted form
        // state directly, bypassing that filter.
        if (! ($this->form->getRawState()['run_match'] ?? false)) {
            return;
        }

        MatchCandidatesToVacancy::dispatch($this->record->id)->afterCommit();

        Notification::make()
            ->title('Matching started')
            ->body('We\'re matching this vacancy against your candidate pool in the background — check the Matches tab shortly.')
            ->success()
            ->send();
    }
}
