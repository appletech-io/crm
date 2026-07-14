<?php

namespace App\Filament\Resources\EducationCandidates\Pages;

use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Jobs\ProcessBulkCvUpload;
use App\Models\CandidateSkill;
use App\Models\CandidateStatus;
use App\Models\Industry;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

class BulkUploadCvs extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = EducationCandidateResource::class;

    protected string $view = 'filament.resources.education-candidates.pages.bulk-upload-cvs';

    public ?array $data = [];

    /** @var array<int, int> */
    public array $skillIds = [];

    public function mount(): void
    {
        $this->form->fill([
            'send_application_email' => true,
        ]);
    }

    /** @return Collection<int, CandidateSkill> */
    #[Computed]
    public function skillOptions(): Collection
    {
        return CandidateSkill::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->orderByRaw('COALESCE(parent_id, id), parent_id IS NOT NULL, name')
            ->get();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('cvs')
                    ->label('CV Files')
                    ->multiple()
                    ->required()
                    ->preserveFilenames()
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(10240)
                    ->disk('local')
                    ->directory('bulk-cv-uploads')
                    ->helperText('Upload one or more CVs (PDF, max 10MB each). A candidate will be created for each file.'),
                Select::make('candidate_status_id')
                    ->label('Status')
                    ->options(fn (): array => CandidateStatus::query()
                        ->where('company_id', Auth::user()->company_id)
                        ->where('industry_id', active_industry_id())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                    )
                    ->required()
                    ->searchable(),
                Toggle::make('send_application_email')
                    ->label('Send applicant email')
                    ->helperText('Invite each created candidate to complete their application.')
                    ->default(true),
            ])
            ->statePath('data');
    }

    public function processCvUploads(): void
    {
        $data = $this->form->getState();

        $modelClass = Industry::candidateModelForSlug(active_industry() ?? '');

        if (! $modelClass) {
            Notification::make()
                ->danger()
                ->title('No candidate type is configured for the active sector.')
                ->send();

            return;
        }

        $files = collect($data['cvs'] ?? [])->values();

        foreach ($files as $path) {
            ProcessBulkCvUpload::dispatch(
                filePath: $path,
                companyId: Auth::user()->company_id,
                industrySlug: active_industry(),
                candidateStatusId: $data['candidate_status_id'],
                skillIds: $this->skillIds,
                sendApplicationEmail: (bool) ($data['send_application_email'] ?? false),
            );
        }

        Notification::make()
            ->success()
            ->title($files->count().' CV(s) queued for processing')
            ->body('Candidates will appear in the list shortly.')
            ->send();

        $this->form->fill([
            'send_application_email' => true,
        ]);

        $this->skillIds = [];
    }
}
