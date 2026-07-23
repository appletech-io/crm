<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Setting a new password for a user on their behalf should require them
     * to change it again, the same as a freshly created user would.
     *
     * @param  array<string, mixed>  $data
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('password', $data)) {
            $data['password_changed_at'] = null;
            $data['requires_account_setup'] = true;
        }

        return $data;
    }
}
