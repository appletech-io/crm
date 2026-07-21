<?php

namespace App\Filament\Resources\HealthcareCandidates\Pages;

use App\Filament\Resources\HealthcareCandidates\HealthcareCandidateResource;
use App\Filament\Resources\HealthcareCandidates\Pages\Concerns\HasCandidateStatusSubheading;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditHealthcareCandidate extends EditRecord
{
    use HasCandidateStatusSubheading;

    protected static string $resource = HealthcareCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return $this->record->first_name
            ? trim("{$this->record->first_name} {$this->record->last_name}")
            : $this->record->email;
    }
}
