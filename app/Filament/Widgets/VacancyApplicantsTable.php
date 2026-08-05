<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Filament\Resources\HealthcareCandidates\HealthcareCandidateResource;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Vacancy;
use App\Models\VacancyApplication;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class VacancyApplicantsTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public ?Vacancy $record = null;

    public function mount(?Vacancy $record = null): void
    {
        $this->record = $record;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->query(fn () => $this->record->applications()->with('candidate'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('candidate.first_name')
                    ->label('Name')
                    ->formatStateUsing(fn (VacancyApplication $record): string => trim("{$record->candidate?->first_name} {$record->candidate?->last_name}") ?: '—'),
                TextColumn::make('candidate.email')
                    ->label('Email')
                    ->placeholder('—'),
                TextColumn::make('match_strength')
                    ->label('Match Strength')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Applied')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('viewCandidate')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (VacancyApplication $record): ?string => match ($record->candidate_type) {
                        EducationCandidate::class => EducationCandidateResource::getUrl('edit', ['record' => $record->candidate_id]),
                        HealthcareCandidate::class => HealthcareCandidateResource::getUrl('edit', ['record' => $record->candidate_id]),
                        default => null,
                    })
                    ->openUrlInNewTab()
                    ->visible(fn (VacancyApplication $record): bool => $record->candidate !== null),
            ])
            ->emptyStateHeading('No applicants yet')
            ->emptyStateDescription('Applications submitted via the public application link will appear here.');
    }
}
