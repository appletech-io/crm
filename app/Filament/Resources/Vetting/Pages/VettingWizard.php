<?php

namespace App\Filament\Resources\Vetting\Pages;

use App\Filament\Resources\EducationCandidates\Pages\Concerns\HasCandidateStatusSubheading;
use App\Filament\Resources\Vetting\Schemas\VettingSteps;
use App\Filament\Resources\Vetting\VettingResource;
use App\Models\EducationCandidate;
use App\Services\CandidateVettingRequirements;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Js;

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

    protected function isComplianceChecklistComplete(): bool
    {
        $record = $this->getRecord();

        return $record instanceof EducationCandidate && CandidateVettingRequirements::isComplete($record);
    }

    protected function getConfirmAndSaveAlpineHandler(): string
    {
        return 'if (confirm('.
            Js::from('Are you sure you want to confirm the compliance process is complete for this candidate? This will record the completion date and who completed it.').
            ')) { $wire.save() }';
    }

    protected function getSubmitFormAction(): Action
    {
        $isComplete = $this->isComplianceChecklistComplete();

        return parent::getSubmitFormAction()
            ->label('Complete')
            ->disabled(! $isComplete)
            ->tooltip($isComplete ? null : 'All vetting checklist requirements must be met before compliance can be completed.')
            ->alpineClickHandler($this->getConfirmAndSaveAlpineHandler());
    }

    public function getWizardComponent(): Component
    {
        $isComplete = $this->isComplianceChecklistComplete();

        return Wizard::make($this->getSteps())
            ->startOnStep($this->getStartStep())
            ->cancelAction($this->getCancelFormAction())
            ->submitAction($this->getSubmitFormAction())
            ->alpineSubmitHandler($isComplete ? $this->getConfirmAndSaveAlpineHandler() : null)
            ->skippable($this->hasSkippableSteps())
            ->contained(false);
    }

    public function getTitle(): string
    {
        return $this->record->first_name
            ? trim("{$this->record->first_name} {$this->record->last_name}")
            : $this->record->email;
    }
}
