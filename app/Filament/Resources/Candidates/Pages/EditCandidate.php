<?php

namespace App\Filament\Resources\Candidates\Pages;

use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Candidates\Schemas\CandidateComplianceForm;
use App\Services\Candidates\FormattedCvGenerator;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCandidate extends EditRecord
{
    protected static string $resource = CandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...CandidateComplianceForm::existingValues($this->getRecord())];
    }

    /**
     * field_{id} keys are synthetic, compliance-value state (see
     * CandidateComplianceForm) — they must not reach $record->update()
     * directly, since they aren't real columns on Candidate.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $candidateData = collect($data)
            ->reject(fn (mixed $value, string $key): bool => str_starts_with($key, 'field_'))
            ->all();

        $record = parent::handleRecordUpdate($record, $candidateData);

        CandidateComplianceForm::saveValues($record, $data);

        return $record;
    }

    protected function afterSave(): void
    {
        $formattedCv = $this->record->formattedCv()->first();

        if ($formattedCv && filled($formattedCv->content)) {
            app(FormattedCvGenerator::class)->regeneratePdf($this->record, $formattedCv);
        }
    }
}
