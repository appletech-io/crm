<?php

namespace App\Filament\Resources\MarketingCampaigns\Pages;

use App\Filament\Resources\MarketingCampaigns\MarketingCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMarketingCampaign extends CreateRecord
{
    protected static string $resource = MarketingCampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['industry_id'] = active_industry_id();

        return $data;
    }
}
