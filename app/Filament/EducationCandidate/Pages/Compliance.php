<?php

namespace App\Filament\EducationCandidate\Pages;

use App\Filament\Resources\Candidates\Schemas\CandidateComplianceForm;
use App\Models\Candidate;
use App\Models\Industry;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The self-service equivalent of the "Compliance" tab on the staff-facing
 * CandidateResource edit page — same schema-builder (CandidateComplianceForm),
 * same field-naming convention, so the two can never drift apart on what a
 * given data_type maps to. Only ever reachable by a generic Candidate;
 * Education/Healthcare candidates use this same panel's Documents page
 * instead (this class lives in that namespace only because the candidate
 * panel discovers pages from here regardless of candidate type — see
 * CandidatePanelProvider).
 */
class Compliance extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.candidate.pages.compliance';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Compliance';

    protected static ?string $title = 'Compliance';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return Industry::candidateModelForSlug(active_industry() ?? '') === Candidate::class;
    }

    public function mount(): void
    {
        $this->form->fill(CandidateComplianceForm::existingValues($this->candidate()));
    }

    public function form(Schema $schema): Schema
    {
        return CandidateComplianceForm::configure($schema, $this->candidate())->statePath('data');
    }

    public function save(): void
    {
        CandidateComplianceForm::saveValues($this->candidate(), $this->form->getState());

        Notification::make()
            ->success()
            ->title('Compliance details saved')
            ->send();
    }

    private function candidate(): Candidate
    {
        /** @var Candidate $candidate */
        $candidate = auth()->user()->candidate;

        return $candidate;
    }
}
