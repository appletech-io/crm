<?php

namespace App\Filament\Resources\Candidates\Pages;

use App\Filament\Concerns\HasPayrollProviderErrorAlert;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Candidates\Schemas\CandidateComplianceForm;
use App\Jobs\SyncPayrollProviderRecord;
use App\Models\Candidate;
use App\Services\Candidates\FormattedCvGenerator;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCandidate extends EditRecord
{
    use HasPayrollProviderErrorAlert;

    protected static string $resource = CandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retryPayrollSync')
                ->label('Retry Payroll Sync')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => $this->hasProviderError($this->record))
                ->action(function (): void {
                    /** @var Candidate $record */
                    $record = $this->record;

                    try {
                        SyncPayrollProviderRecord::dispatchSync($record);
                    } catch (\Throwable) {
                        // recordFailure() inside the job already persisted
                        // the error detail — the check below picks it up.
                    }

                    if ($this->hasProviderError($record)) {
                        Notification::make()
                            ->danger()
                            ->title('Retry failed — see the error below')
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Payroll sync retried successfully')
                        ->success()
                        ->send();
                }),
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
