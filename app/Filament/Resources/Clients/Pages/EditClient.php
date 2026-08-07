<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Enums\EmailTemplateAudience;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Support\SendCustomEmailAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendCustomEmailAction::header(EmailTemplateAudience::Client),
            DeleteAction::make()
                ->visible(fn (): bool => Auth::user()?->isAdmin() ?? false),
            ForceDeleteAction::make()
                ->visible(fn (): bool => Auth::user()?->isAdmin() ?? false),
            RestoreAction::make(),
        ];
    }
}
