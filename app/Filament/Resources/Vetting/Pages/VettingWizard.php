<?php

namespace App\Filament\Resources\Vetting\Pages;

use App\Filament\Resources\EducationCandidates\Pages\Concerns\HasCandidateStatusSubheading;
use App\Filament\Resources\Vetting\Schemas\VettingSteps;
use App\Filament\Resources\Vetting\VettingResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\Auth;

class VettingWizard extends EditRecord
{
    use EditRecord\Concerns\HasWizard;
    use HasCandidateStatusSubheading;

    protected static string $resource = VettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /** @return array<int, Step> */
    public function getSteps(): array
    {
        return VettingSteps::steps();
    }

    public function getStartStep(): int
    {
        return $this->record->compliance_step ?? 1;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['compliance_completed_at'] = now();
        $data['compliance_completed_by'] = Auth::id();

        return $data;
    }

    public function getTitle(): string
    {
        return $this->record->first_name
            ? trim("{$this->record->first_name} {$this->record->last_name}")
            : $this->record->email;
    }
}
