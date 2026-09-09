<?php

namespace App\Filament\Resources\SampleProfiles\Pages;

use App\Filament\Resources\SampleProfiles\SampleProfileResource;
use App\Models\SampleProfile;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSampleProfile extends CreateRecord
{
    protected static string $resource = SampleProfileResource::class;

    protected function beforeCreate(): void
    {
        $count = SampleProfile::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->count();

        if ($count >= SampleProfileResource::MAX_PER_SECTOR) {
            Notification::make()
                ->danger()
                ->title('Up to '.SampleProfileResource::MAX_PER_SECTOR.' sample profiles are allowed per sector')
                ->body('Delete an existing one before adding another.')
                ->send();

            $this->halt();
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['industry_id'] = active_industry_id();
        $data['uploaded_by'] = Auth::id();

        return $data;
    }
}
