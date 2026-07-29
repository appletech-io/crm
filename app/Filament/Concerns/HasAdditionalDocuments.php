<?php

namespace App\Filament\Concerns;

use App\Models\CandidateDocument;
use App\Services\Candidates\CandidateDocumentRequirements;
use App\Services\Candidates\Document;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * "Additional" documents (references, qualifications, and other miscellaneous
 * files) are the only document types a candidate can upload more than one of.
 * Used by the staff-side candidate edit page's Documents tab (type is picked
 * from a dropdown) and the compliance wizard's References step (type is
 * always "reference", so no dropdown is shown) only — deliberately not part
 * of {@see CandidateDocumentRequirements}, since these are optional and must
 * not affect required-document completeness.
 */
trait HasAdditionalDocuments
{
    abstract protected function additionalDocumentsCandidate(): ?Model;

    /**
     * The types offered by {@see addAdditionalDocumentAction()}'s dropdown and
     * scoped by {@see additionalDocuments()}. Override this in the using class
     * to restrict which types are available in that context — the candidate
     * self-service portal excludes "reference", for example.
     *
     * @return array<string, string>
     */
    protected function additionalDocumentTypes(): array
    {
        return [
            'reference' => 'Reference',
            'other' => 'Other',
            'qualification' => 'Qualification',
        ];
    }

    /** @return Collection<int, CandidateDocument> */
    public function additionalDocuments(): Collection
    {
        $candidate = $this->additionalDocumentsCandidate();

        if (! $candidate) {
            return new Collection;
        }

        return $candidate->documents()
            ->whereIn('document_type', array_keys($this->additionalDocumentTypes()))
            ->oldest()
            ->get();
    }

    /** @return array<string, array{document_type: string, label: string, description: string, uploaded: bool, path: ?string, url: ?string, additional_document_id: int}> */
    public function additionalDocumentRows(): array
    {
        $countsByType = [];
        $types = $this->additionalDocumentTypes();

        return $this->additionalDocuments()
            ->mapWithKeys(function (CandidateDocument $document) use (&$countsByType, $types): array {
                $type = $document->document_type->value;
                $countsByType[$type] = ($countsByType[$type] ?? 0) + 1;

                $label = $document->name ?: ($types[$type] ?? $document->document_type->label()).' '.$countsByType[$type];

                return ["additional_document_{$document->id}" => [
                    'document_type' => $type,
                    'label' => $label,
                    'description' => "An additional {$type} document for this candidate.",
                    'uploaded' => true,
                    'path' => $document->path,
                    'url' => null,
                    'additional_document_id' => $document->id,
                ]];
            })
            ->all();
    }

    /**
     * For contexts where staff choose the document's type from a dropdown
     * (the candidate edit page's Documents tab, and the candidate portal's
     * own Documents page, each with its own {@see additionalDocumentTypes()}).
     */
    public function addAdditionalDocumentAction(bool $withName = false): Action
    {
        $schema = [];

        if ($withName) {
            $schema[] = TextInput::make('name')
                ->label('Name')
                ->helperText('Optional — used to label this document instead of its type.')
                ->maxLength(255);
        }

        $schema[] = Select::make('document_type')
            ->label('Type')
            ->options($this->additionalDocumentTypes())
            ->native(false)
            ->required();

        $schema[] = FileUpload::make('file')
            ->label('File')
            ->storeFiles(false)
            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
            ->maxSize(10240)
            ->required();

        return $this->buildAddDocumentAction($schema);
    }

    /**
     * For contexts where the document is always a reference (the compliance
     * wizard's References step), so no type picker is shown.
     */
    public function addReferenceDocumentAction(): Action
    {
        return $this->buildAddDocumentAction([
            FileUpload::make('file')
                ->label('File')
                ->storeFiles(false)
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->maxSize(10240)
                ->required(),
        ], fixedDocumentType: 'reference')
            ->label('Add Reference')
            ->modalHeading('Add Reference');
    }

    /** @param  array<int, Component>  $schema */
    private function buildAddDocumentAction(array $schema, ?string $fixedDocumentType = null): Action
    {
        return Action::make('addDocument')
            ->label('Add Document')
            ->icon('heroicon-o-plus')
            ->color('primary')
            ->modalHeading('Add Document')
            ->schema($schema)
            ->action(function (array $data) use ($fixedDocumentType): void {
                $candidate = $this->additionalDocumentsCandidate();

                if (! $candidate) {
                    return;
                }

                /** @var TemporaryUploadedFile $file */
                $file = $data['file'];
                $documentType = $fixedDocumentType ?? $data['document_type'];

                $path = Document::upload($file, $candidate, $documentType.'-'.Str::random(8));

                $candidate->documents()->create([
                    'document_type' => $documentType,
                    'name' => $data['name'] ?? null,
                    'path' => $path,
                ]);

                $this->resetTable();

                Notification::make()
                    ->success()
                    ->title('Document added')
                    ->send();
            });
    }

    public function removeAdditionalDocument(int $documentId): void
    {
        $candidate = $this->additionalDocumentsCandidate();

        if (! $candidate) {
            return;
        }

        $document = $candidate->documents()
            ->where('id', $documentId)
            ->whereIn('document_type', array_keys($this->additionalDocumentTypes()))
            ->first();

        if (! $document) {
            return;
        }

        Storage::disk(config('filesystems.default'))->delete($document->path);
        $document->delete();

        $this->resetTable();

        Notification::make()
            ->success()
            ->title('Document removed')
            ->send();
    }
}
