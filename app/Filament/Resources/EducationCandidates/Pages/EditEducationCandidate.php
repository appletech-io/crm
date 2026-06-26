<?php

namespace App\Filament\Resources\EducationCandidates\Pages;

use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Filament\Resources\EducationCandidates\Pages\Concerns\HasCandidateStatusSubheading;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditEducationCandidate extends EditRecord
{
    use HasCandidateStatusSubheading;

    protected static string $resource = EducationCandidateResource::class;

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
