<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Vacancies\VacancyResource;
use App\Models\Client;
use App\Models\JobStatus;
use App\Models\Vacancy;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ClientPipelineOverview extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public ?Client $record = null;

    public function mount(?Client $record = null): void
    {
        $this->record = $record;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->query(fn () => $this->record->vacancies())
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Job')
                    ->searchable(),
                TextColumn::make('jobTitle.name')
                    ->label('Job Title')
                    ->placeholder('—'),
                TextColumn::make('jobStatus.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Vacancy $record): string => $record->jobStatus?->color ?? 'gray'),
                TextColumn::make('positions_available')
                    ->label('Positions'),
                TextColumn::make('salary_min')
                    ->label('Salary')
                    ->money('GBP')
                    ->placeholder('—'),
                TextColumn::make('salary_max')
                    ->label('to')
                    ->money('GBP')
                    ->placeholder('—'),
                TextColumn::make('placement_fee_percentage')
                    ->label('Fee %')
                    ->formatStateUsing(fn (?float $state): string => $state !== null ? "{$state}%" : '—'),
                TextColumn::make('estimated_value')
                    ->label('Est. Value')
                    ->state(fn (Vacancy $record): ?float => $record->estimatedPlacementValue())
                    ->money('GBP')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('job_status_id')
                    ->label('Status')
                    ->options(fn (): array => JobStatus::query()
                        ->where('company_id', $this->record->company_id)
                        ->where('industry_id', $this->record->industry_id)
                        ->pluck('name', 'id')
                        ->toArray()
                    ),
            ])
            ->recordActions([
                Action::make('viewJob')
                    ->label('View Job')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Vacancy $record): string => VacancyResource::getUrl('edit', ['record' => $record->id]))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('No jobs for this client yet')
            ->emptyStateDescription('Jobs linked to this client will appear here, along with an estimated placement value once a salary and fee % are set.');
    }
}
