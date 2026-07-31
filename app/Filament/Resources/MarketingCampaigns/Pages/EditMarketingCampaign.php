<?php

namespace App\Filament\Resources\MarketingCampaigns\Pages;

use App\Filament\Resources\MarketingCampaigns\MarketingCampaignResource;
use App\Filament\Support\SendCustomEmailAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditMarketingCampaign extends EditRecord
{
    protected static string $resource = MarketingCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendCustomEmailAction::campaign($this->record),
            DeleteAction::make()
                ->visible(fn (): bool => Auth::user()?->isAdmin() ?? false),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
