<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Filament\Resources\HealthcareCandidates\HealthcareCandidateResource;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Vacancy;
use App\Models\VacancyPlacement;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class VacancyPlacementsTable extends TableWidget
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
            ->query(fn () => $this->record->placements()->with('candidate'))
            ->defaultSort('placed_at', 'desc')
            ->columns([
                TextColumn::make('candidate.first_name')
                    ->label('Name')
                    ->formatStateUsing(fn (VacancyPlacement $record): string => trim("{$record->candidate?->first_name} {$record->candidate?->last_name}") ?: '—'),
                TextColumn::make('candidate.email')
                    ->label('Email')
                    ->placeholder('—'),
                TextColumn::make('actual_salary')
                    ->label('Actual Salary')
                    ->formatStateUsing(fn (?float $state): string => $state !== null ? '£'.number_format($state, 2) : '—'),
                TextColumn::make('placed_at')
                    ->label('Placed')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('editSalary')
                    ->label('Edit Salary')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->schema([
                        TextInput::make('actual_salary')
                            ->label('Actual Salary')
                            ->numeric()
                            ->prefix('£')
                            ->required(),
                    ])
                    ->fillForm(fn (VacancyPlacement $record): array => ['actual_salary' => $record->actual_salary])
                    ->action(fn (VacancyPlacement $record, array $data): bool => $record->update(['actual_salary' => $data['actual_salary']])),
                Action::make('viewCandidate')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (VacancyPlacement $record): ?string => match ($record->candidate_type) {
                        EducationCandidate::class => EducationCandidateResource::getUrl('edit', ['record' => $record->candidate_id]),
                        HealthcareCandidate::class => HealthcareCandidateResource::getUrl('edit', ['record' => $record->candidate_id]),
                        default => null,
                    })
                    ->openUrlInNewTab()
                    ->visible(fn (VacancyPlacement $record): bool => $record->candidate !== null),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No placements yet')
            ->emptyStateDescription('Use "Mark as Placed" on the Applicants or Matches tab to record a placement here.');
    }
}
