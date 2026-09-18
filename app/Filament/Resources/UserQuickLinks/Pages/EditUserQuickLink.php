<?php

namespace App\Filament\Resources\UserQuickLinks\Pages;

use App\Filament\Resources\UserQuickLinks\UserQuickLinkResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUserQuickLink extends EditRecord
{
    protected static string $resource = UserQuickLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
