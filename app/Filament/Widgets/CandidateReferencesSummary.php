<?php

namespace App\Filament\Widgets;

use App\Enums\ReferenceStatus;
use App\Enums\ReferenceType;
use App\Models\CandidateReference;
use App\Services\References\ReferenceResponsePdfService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A compact summary of the candidate's references, shown above the required
 * documents table in the compliance wizard so staff can jump straight to a
 * referee's read-only response while reviewing compliance.
 */
class CandidateReferencesSummary extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 0;

    public ?Model $record = null;

    public function mount(?Model $record = null): void
    {
        $this->record = $record;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('References')
            ->query(fn () => $this->record?->references() ?? CandidateReference::query()->whereRaw('1 = 0'))
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->state(fn (CandidateReference $record): string => $record->type === ReferenceType::GapStatement
                        ? 'Gap / Statement'
                        : trim("{$record->first_name} {$record->last_name}")),

                TextColumn::make('type')
                    ->label('Type')
                    ->formatStateUsing(fn (CandidateReference $record): string => $record->type->label()),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (CandidateReference $record): string => $record->status->label())
                    ->color(fn (CandidateReference $record): string => $record->status->color()),
            ])
            ->recordActions([
                Action::make('viewResponse')
                    ->label('View Response')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (CandidateReference $record): ?string => $record->token
                        ? route('reference.form', ['token' => $record->token])
                        : null
                    )
                    ->openUrlInNewTab()
                    ->visible(fn (CandidateReference $record): bool => filled($record->token)),
                Action::make('downloadPdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (CandidateReference $record): bool => in_array($record->status, [ReferenceStatus::Submitted, ReferenceStatus::Confirmed], true))
                    ->action(fn (CandidateReference $record): StreamedResponse => response()->streamDownload(
                        fn () => print (app(ReferenceResponsePdfService::class)->generate($record)),
                        app(ReferenceResponsePdfService::class)->filename($record)
                    )),
            ])
            ->paginated(false)
            ->emptyStateHeading('No references')
            ->emptyStateDescription('References added by the candidate during their application will appear here.');
    }
}
