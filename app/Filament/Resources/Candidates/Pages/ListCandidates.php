<?php

namespace App\Filament\Resources\Candidates\Pages;

use App\Actions\Candidates\GenericCandidateCreated;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Models\Candidate;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCandidates extends ListRecords
{
    protected static string $resource = CandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // A job title may not be set yet on the create form, in which
            // case GenericCandidateCreated is a no-op — staff can send the
            // portal invite later from the edit page once one is assigned.
            CreateAction::make()
                ->after(function (Candidate $record): void {
                    GenericCandidateCreated::run($record);
                }),
        ];
    }
}
