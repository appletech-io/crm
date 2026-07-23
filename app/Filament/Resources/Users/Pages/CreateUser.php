<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * A site admin sets this user's initial password directly, so they must
     * change it — and set up two-factor authentication — before doing
     * anything else.
     *
     * @param  array<string, mixed>  $data
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requires_account_setup'] = true;

        return $data;
    }
}
