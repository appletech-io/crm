<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasAdditionalDocuments;
use App\Services\Candidates\Document;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;

/**
 * Powers the "References" step of the compliance wizard, letting staff add reference
 * documents for a candidate — every document added here is already known to be a
 * reference, so there's no type picker (that only appears on the candidate edit
 * page's Documents tab, via {@see CandidateDocumentManager}). Any "other" documents
 * added there still show up in this list, just can't be added from here. These are
 * never required, so they're kept entirely separate from {@see CandidateDocumentStatus}
 * and the vetting checklist.
 */
class CandidateAdditionalDocuments extends TableWidget
{
    use HasAdditionalDocuments;

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    public function mount(?Model $record = null): void
    {
        $this->record = $record;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->additionalDocumentRows())
            ->headerActions([
                $this->addReferenceDocumentAction(),
            ])
            ->columns([
                TextColumn::make('label')
                    ->label('Document'),

                TextColumn::make('description')
                    ->label('Description')
                    ->color('gray')
                    ->wrap(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (array $record): ?string => Document::viewUrl($record['path']))
                    ->openUrlInNewTab(),

                Action::make('removeAdditionalDocument')
                    ->label('Remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remove document')
                    ->modalDescription('Are you sure you want to remove this document?')
                    ->action(fn (array $record) => $this->removeAdditionalDocument($record['additional_document_id'])),
            ])
            ->paginated(false)
            ->emptyStateHeading('No additional documents')
            ->emptyStateDescription('Add a reference letter or other document using the button above.');
    }

    protected function additionalDocumentsCandidate(): ?Model
    {
        return $this->record;
    }
}
