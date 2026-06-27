<?php

namespace App\Filament\Resources\CandidatePools\Pages;

use App\Filament\Resources\CandidatePools\CandidatePoolResource;
use App\Filament\Resources\CandidatePools\RelationManagers\CandidatesRelationManager;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCandidatePool extends EditRecord
{
    protected static string $resource = CandidatePoolResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function getRelationManagers(): array
    {
        return [
            CandidatesRelationManager::class,
        ];
    }
}
