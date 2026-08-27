<?php

namespace App\Filament\Resources\CandidateCompliance\Pages;

use App\Filament\Resources\CandidateCompliance\CandidateComplianceResource;
use App\Filament\Resources\Candidates\Schemas\CandidateComplianceForm;
use App\Models\Candidate;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EditCandidateCompliance extends EditRecord
{
    protected static string $resource = CandidateComplianceResource::class;

    public function form(Schema $schema): Schema
    {
        /** @var Candidate $record */
        $record = $this->getRecord();

        return CandidateComplianceForm::configure($schema, $record);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        return [...$data, ...CandidateComplianceForm::existingValues($record)];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        CandidateComplianceForm::saveValues($record, $data);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
