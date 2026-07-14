<?php

namespace App\Filament\EducationCandidate\Pages;

use App\Models\EducationCandidate;
use App\Services\Education\CandidateDocumentRequirements;
use App\Services\Education\Document;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class Documents extends Page implements HasTable
{
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
        return CandidateDocumentRequirements::for($this->candidate());
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
                Action::make('preventTrainingInfo')
                    ->label('')
                    ->icon('heroicon-o-information-circle')
                    ->color('gray')
                    ->tooltip('More information')
                    ->visible(fn (array $record): bool => $record['document_type'] === 'prevent_training')
                    ->modalHeading('Prevent Training')
                    ->modalContent(view('filament.candidate.pages.documents.prevent-training-info'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

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
                    ->visible(fn (array $record): bool => $record['url'] === null && $record['uploaded']),

                Action::make('remove')
                    ->label('Remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remove document')
                    ->modalDescription('Are you sure you want to remove this document? You will need to upload it again.')
                    ->visible(fn (array $record): bool => $record['url'] === null && $record['uploaded'])
                    ->action(fn (array $record) => $this->removeDocument($record['document_type'])),
            ]);
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

    private function uploadDocument(string $documentType, UploadedFile $file): void
    {
        $candidate = $this->candidate();

        $path = Document::upload($file, $candidate, $documentType);

        $existing = $candidate->documents()->where('document_type', $documentType)->first();

        if ($existing) {
            Storage::disk('local')->delete($existing->path);
            $existing->update(['path' => $path]);
        } else {
            $candidate->documents()->create([
                'document_type' => $documentType,
                'path' => $path,
            ]);
        }

        if (in_array($documentType, ['dbs_front', 'dbs_back'], true) && $candidate->has_dbs !== 'yes') {
            $candidate->update(['has_dbs' => 'yes']);
        }

        Notification::make()
            ->success()
            ->title('Document uploaded')
            ->send();
    }

    private function removeDocument(string $documentType): void
    {
        $candidate = $this->candidate();

        $document = $candidate->documents()->where('document_type', $documentType)->first();

        if ($document) {
            Storage::disk('local')->delete($document->path);
            $document->delete();
        }

        Notification::make()
            ->success()
            ->title('Document removed')
            ->send();
    }

    private function candidate(): EducationCandidate
    {
        /** @var EducationCandidate $candidate */
        $candidate = auth()->user()->candidate;

        return $candidate;
    }
}
