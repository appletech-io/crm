<?php

namespace App\Filament\Resources\UserQuickLinks\Pages;

use App\Filament\Resources\UserQuickLinks\UserQuickLinkResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateUserQuickLink extends CreateRecord
{
    protected static string $resource = UserQuickLinkResource::class;

    /**
     * The header "New" action already hides itself at the cap (see
     * ListUserQuickLinks), but this catches anyone who navigates to
     * /create directly once they're already at the limit.
     */
    public function mount(): void
    {
        if (UserQuickLinkResource::getEloquentQuery()->count() >= UserQuickLinkResource::MAX_QUICK_LINKS) {
            Notification::make()
                ->title('You can only have up to '.UserQuickLinkResource::MAX_QUICK_LINKS.' quick links')
                ->warning()
                ->send();

            $this->redirect(UserQuickLinkResource::getUrl('index'));

            return;
        }

        parent::mount();
    }
}
