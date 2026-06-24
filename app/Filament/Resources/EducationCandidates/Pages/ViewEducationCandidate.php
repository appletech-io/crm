<?php

namespace App\Filament\Resources\EducationCandidates\Pages;

use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEducationCandidate extends ViewRecord
{
    protected static string $resource = EducationCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
