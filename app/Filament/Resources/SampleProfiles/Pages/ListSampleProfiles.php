<?php

namespace App\Filament\Resources\SampleProfiles\Pages;

use App\Filament\Resources\SampleProfiles\SampleProfileResource;
use App\Models\SampleProfile;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListSampleProfiles extends ListRecords
{
    protected static string $resource = SampleProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->disabled(fn (): bool => $this->sampleCount() >= SampleProfileResource::MAX_PER_SECTOR)
                ->tooltip(fn (): ?string => $this->sampleCount() >= SampleProfileResource::MAX_PER_SECTOR
                    ? 'Up to '.SampleProfileResource::MAX_PER_SECTOR.' sample profiles are allowed per sector — delete one to add another.'
                    : null),
        ];
    }

    private function sampleCount(): int
    {
        return SampleProfile::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->count();
    }
}
