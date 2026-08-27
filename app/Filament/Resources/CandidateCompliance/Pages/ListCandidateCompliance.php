<?php

namespace App\Filament\Resources\CandidateCompliance\Pages;

use App\Filament\Resources\CandidateCompliance\CandidateComplianceResource;
use Filament\Resources\Pages\ListRecords;

class ListCandidateCompliance extends ListRecords
{
    protected static string $resource = CandidateComplianceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
