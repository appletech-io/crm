<?php

namespace App\Filament\Resources\UserQuickLinks\Pages;

use App\Filament\Resources\UserQuickLinks\UserQuickLinkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUserQuickLinks extends ListRecords
{
    protected static string $resource = UserQuickLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => UserQuickLinkResource::getEloquentQuery()->count() < UserQuickLinkResource::MAX_QUICK_LINKS),
        ];
    }
}
