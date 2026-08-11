<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Filament\Resources\HealthcareCandidates\HealthcareCandidateResource;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Vacancy;
use App\Models\VacancyCandidateMatch;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class VacancyMatchesTable extends TableWidget
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
            ->query(fn () => $this->record->matches()->with('candidate'))
            ->defaultSort('score', 'desc')
            ->columns([
                TextColumn::make('score')
                    ->label('Match')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 70 => 'success',
                        $state >= 40 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (int $state): string => "{$state}%")
                    ->sortable(),
                TextColumn::make('candidate.first_name')
                    ->label('Name')
                    ->formatStateUsing(fn (VacancyCandidateMatch $record): string => trim("{$record->candidate?->first_name} {$record->candidate?->last_name}") ?: '—'),
                TextColumn::make('candidate.email')
                    ->label('Email')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Matched')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('viewCandidate')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (VacancyCandidateMatch $record): ?string => match ($record->candidate_type) {
                        EducationCandidate::class => EducationCandidateResource::getUrl('edit', ['record' => $record->candidate_id]),
                        HealthcareCandidate::class => HealthcareCandidateResource::getUrl('edit', ['record' => $record->candidate_id]),
                        default => null,
                    })
                    ->openUrlInNewTab()
                    ->visible(fn (VacancyCandidateMatch $record): bool => $record->candidate !== null),
            ])
            ->emptyStateHeading('No matches yet')
            ->emptyStateDescription('Run a match from this vacancy\'s edit page to rank your candidate pool against it.');
    }
}
