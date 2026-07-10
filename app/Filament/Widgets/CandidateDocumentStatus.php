<?php

namespace App\Filament\Widgets;

use App\Models\EducationCandidate;
use App\Services\CandidateDocumentRequirements;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CandidateDocumentStatus extends TableWidget
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
        if (! $this->record instanceof EducationCandidate) {
            return [];
        }

        return CandidateDocumentRequirements::for($this->record, includeGetDbsAction: false);
    }

    public function table(Table $table): Table
    {
        return $table
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
                        ? Storage::disk('local')->temporaryUrl($record['path'], now()->addMinutes(10))
                        : null
                    )
                    ->openUrlInNewTab()
                    ->visible(fn (array $record): bool => $record['uploaded']),
            ])
            ->paginated(false);
    }
}
