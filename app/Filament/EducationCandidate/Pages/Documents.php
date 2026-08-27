<?php

namespace App\Filament\EducationCandidate\Pages;

use App\Enums\DocumentType;
use App\Filament\Concerns\HasAdditionalDocuments;
use App\Jobs\GenerateFormattedCv;
use App\Models\CandidateComplianceValue;
use App\Services\Candidates\CandidateDocumentRequirements;
use App\Services\Candidates\Document;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class Documents extends Page implements HasTable
{
    use HasAdditionalDocuments;
    use InteractsWithTable;

    protected string $view = 'filament.candidate.pages.documents';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Documents';

    protected static ?string $title = 'Documents';

    public string $activeTab = 'actions';

    public function getHeading(): ?string
    {
        return null;
    }

    /** @return array<string, array{document_type: string, label: string, description: string, uploaded: bool, path: ?string, url: ?string}> */
    public function documentTypes(): array
    {
        return CandidateDocumentRequirements::for($this->candidate()) + $this->additionalDocumentRows();
    }

    /**
     * References are added by staff only, on the candidate edit page — never
     * shown or addable here.
     *
     * @return array<string, string>
     */
    protected function additionalDocumentTypes(): array
    {
        return [
            'other' => 'Other',
            'qualification' => 'Qualification',
        ];
    }

    protected function additionalDocumentsCandidate(): ?Model
    {
        return $this->candidate();
    }

    /** @return array<string, array{document_type: string, label: string, description: string, uploaded: bool, path: ?string, url: ?string}> */
    private function visibleRows(): array
    {
        return collect($this->documentTypes())
            ->filter(fn (array $row): bool => $this->activeTab === 'documents' ? $row['uploaded'] : ! $row['uploaded'])
            ->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->visibleRows())
            ->headerActions([
                $this->addAdditionalDocumentAction(),
            ])
            ->columns([
                TextColumn::make('label')
                    ->label('Document'),

                TextColumn::make('description')
                    ->label('Description')
                    ->color('gray')
                    ->wrap(),

                TextColumn::make('uploaded')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state, array $record): string => match (true) {
                        $record['url'] !== null => 'Action needed',
                        $state => 'Uploaded',
                        default => 'Not uploaded',
                    })
                    ->color(fn (bool $state, array $record): string => match (true) {
                        $record['url'] !== null => 'warning',
                        $state => 'success',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                Action::make('getDbs')
                    ->label('Get your DBS')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('primary')
                    ->url(fn (array $record): ?string => $record['url'])
                    ->openUrlInNewTab()
                    ->visible(fn (array $record): bool => $record['url'] !== null),

                $this->uploadAction('upload', 'Upload', 'heroicon-o-arrow-up-tray')
                    ->visible(fn (array $record): bool => $record['url'] === null && ! $record['uploaded']),

                $this->uploadAction('update', 'Update', 'heroicon-o-arrow-path')
                    ->visible(fn (array $record): bool => $record['url'] === null && $record['uploaded'] && ! $this->isAdditionalDocumentRecord($record)),

                Action::make('remove')
                    ->label('Remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remove document')
                    ->modalDescription('Are you sure you want to remove this document? You will need to upload it again.')
                    ->visible(fn (array $record): bool => $record['url'] === null && $record['uploaded'] && ! $this->isAdditionalDocumentRecord($record))
                    ->action(fn (array $record) => $this->removeDocument($record['document_type'])),

                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (array $record): ?string => Document::viewUrl($record['path']))
                    ->openUrlInNewTab()
                    ->visible(fn (array $record): bool => $this->isAdditionalDocumentRecord($record)),

                Action::make('removeAdditionalDocument')
                    ->label('Remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remove document')
                    ->modalDescription('Are you sure you want to remove this document?')
                    ->visible(fn (array $record): bool => $this->isAdditionalDocumentRecord($record))
                    ->action(fn (array $record) => $this->removeAdditionalDocument($record['additional_document_id'])),
            ]);
    }

    private function isAdditionalDocumentRecord(array $record): bool
    {
        return isset($record['additional_document_id']);
    }

    private function uploadAction(string $name, string $label, string $icon): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color('primary')
            ->modalHeading(fn (array $record): string => "{$label} {$record['label']}")
            ->schema([
                FileUpload::make('file')
                    ->label('File')
                    ->storeFiles(false)
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(10240)
                    ->required(),
            ])
            ->action(fn (array $data, array $record) => $this->uploadDocument($record['document_type'], $data['file']));
    }

    private function uploadDocument(string $documentType, TemporaryUploadedFile $file): void
    {
        $candidate = $this->candidate();

        if (str_starts_with($documentType, 'compliance_field_')) {
            $this->uploadComplianceDocument($candidate, $documentType, $file);

            return;
        }

        $path = Document::upload($file, $candidate, $documentType);

        $existing = $candidate->documents()->where('document_type', $documentType)->first();

        if ($existing) {
            Storage::disk(config('filesystems.default'))->delete($existing->path);
            $existing->update(['path' => $path]);
            $document = $existing;
        } else {
            $document = $candidate->documents()->create([
                'document_type' => $documentType,
                'path' => $path,
            ]);
        }

        if (in_array($documentType, ['dbs_front', 'dbs_back'], true) && $candidate->has_dbs !== 'yes') {
            $candidate->update(['has_dbs' => 'yes']);
        }

        if ($documentType === DocumentType::Cv->value) {
            GenerateFormattedCv::dispatch($candidate, $document);
        }

        $this->resetTable();

        Notification::make()
            ->success()
            ->title('Document uploaded')
            ->send();
    }

    /**
     * A document-type Compliance Item field, merged into this same list by
     * CandidateDocumentRequirements — belongs on CandidateComplianceValue,
     * not CandidateDocument, so it's saved the same way
     * CandidateComplianceForm::saveValues() would for a Document field.
     */
    private function uploadComplianceDocument(Model $candidate, string $documentType, TemporaryUploadedFile $file): void
    {
        $fieldId = (int) str_replace('compliance_field_', '', $documentType);
        $path = Document::upload($file, $candidate, $documentType);

        CandidateComplianceValue::updateOrCreate(
            ['candidate_id' => $candidate->id, 'compliance_item_field_id' => $fieldId],
            ['document_path' => $path, 'document_name' => basename($path), 'completed_at' => now()],
        );

        $this->resetTable();

        Notification::make()
            ->success()
            ->title('Document uploaded')
            ->send();
    }

    private function removeDocument(string $documentType): void
    {
        $candidate = $this->candidate();

        if (str_starts_with($documentType, 'compliance_field_')) {
            $this->removeComplianceDocument($candidate, $documentType);

            return;
        }

        $document = $candidate->documents()->where('document_type', $documentType)->first();

        if ($document) {
            Storage::disk(config('filesystems.default'))->delete($document->path);
            $document->delete();
        }

        $this->resetTable();

        Notification::make()
            ->success()
            ->title('Document removed')
            ->send();
    }

    private function removeComplianceDocument(Model $candidate, string $documentType): void
    {
        $fieldId = (int) str_replace('compliance_field_', '', $documentType);

        $value = CandidateComplianceValue::where('candidate_id', $candidate->id)
            ->where('compliance_item_field_id', $fieldId)
            ->first();

        if ($value?->document_path) {
            Storage::disk(config('filesystems.default'))->delete($value->document_path);
            $value->update(['document_path' => null, 'document_name' => null, 'completed_at' => null]);
        }

        $this->resetTable();

        Notification::make()
            ->success()
            ->title('Document removed')
            ->send();
    }

    private function candidate(): Model
    {
        /** @var Model $candidate */
        $candidate = auth()->user()->candidate;

        return $candidate;
    }
}
