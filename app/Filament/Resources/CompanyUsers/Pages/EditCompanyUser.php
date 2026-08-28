<?php

namespace App\Filament\Resources\CompanyUsers\Pages;

use App\Filament\Resources\CompanyUsers\CompanyUserResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCompanyUser extends EditRecord
{
    protected static string $resource = CompanyUserResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Roles is a CheckboxList of just CompanyUserResource::ASSIGNABLE_ROLES,
     * dehydrated(false) on the form (see CompanyUserForm) so it never
     * reaches $data as a plain column — the raw submitted state is read
     * here instead, since only this subset should ever be touched by an
     * admin. syncRoles() with anything outside that set (e.g. this user
     * already being an 'admin') would wipe it if we didn't preserve it.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $submittedRoles = $this->form->getRawState()['roles'] ?? [];

        $retainedRoles = $record->roles->pluck('name')
            ->diff(CompanyUserResource::ASSIGNABLE_ROLES)
            ->values()
            ->toArray();

        $record->syncRoles([...$retainedRoles, ...$submittedRoles]);

        return parent::handleRecordUpdate($record, $data);
    }
}
