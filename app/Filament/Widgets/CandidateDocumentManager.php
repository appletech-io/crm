<?php

namespace App\Filament\Widgets;

use App\Services\Candidates\CandidateDocumentRequirements;
use App\Services\Candidates\Document;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CandidateDocumentManager extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    public function mount(?Model $record = null): void
    {
        $this->record = $record;
    }

    /** @return array<string, array{document_type: string, label: string, description: string, uploaded: bool, path: ?string, url: ?string}> */
    private function rows(): array
    {
        if (! $this->record) {
            return [];
        }

        return CandidateDocumentRequirements::for($this->record, includeGetDbsAction: false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->records(fn (): array => $this->rows())
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
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Uploaded' : 'Not uploaded')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (array $record): ?string => $record['path']
                        ? Storage::disk(config('filesystems.default'))->temporaryUrl($record['path'], now()->addMinutes(10))
                        : null
                    )
                    ->openUrlInNewTab()
                    ->visible(fn (array $record): bool => $record['uploaded']),

                $this->uploadAction('upload', 'Upload', 'heroicon-o-arrow-up-tray')
                    ->visible(fn (array $record): bool => ! $record['uploaded']),

                $this->uploadAction('update', 'Update', 'heroicon-o-arrow-path')
                    ->visible(fn (array $record): bool => $record['uploaded']),

                Action::make('remove')
                    ->label('Remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remove document')
                    ->modalDescription('Are you sure you want to remove this document? The candidate will need to provide it again.')
                    ->visible(fn (array $record): bool => $record['uploaded'])
                    ->action(fn (array $record) => $this->removeDocument($record['document_type'])),
            ])
            ->paginated(false);
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
        $path = Document::upload($file, $this->record, $documentType);

        $existing = $this->record->documents()->where('document_type', $documentType)->first();

        if ($existing) {
            Storage::disk(config('filesystems.default'))->delete($existing->path);
            $existing->update(['path' => $path]);
        } else {
            $this->record->documents()->create([
                'document_type' => $documentType,
                'path' => $path,
            ]);
        }

        if (in_array($documentType, ['dbs_front', 'dbs_back'], true) && $this->record->has_dbs !== 'yes') {
            $this->record->update(['has_dbs' => 'yes']);
        }

        $this->resetTable();

        Notification::make()
            ->success()
            ->title('Document uploaded')
            ->send();
    }

    private function removeDocument(string $documentType): void
    {
        $document = $this->record->documents()->where('document_type', $documentType)->first();

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
}
