<?php

namespace App\Filament\Resources\EducationVetting\Tables;

use App\Filament\Resources\EducationVetting\VettingResource;
use App\Models\EducationCandidate;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VettingTable
{
    /** @var array<int, string> */
    protected const STEP_LABELS = [
        'Personal Details',
        'Pay Rates',
        'Skills',
        'Documents',
        'Security Checks',
        'TRA Checks',
        'DBS',
        'References',
        'Confirm',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('first_name')
                    ->label('First Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->label('Last Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vetting_progress')
                    ->label('Vetting Progress')
                    ->badge()
                    ->state(fn (EducationCandidate $record): string => static::vettingProgressLabel($record))
                    ->color(fn (EducationCandidate $record): string => static::vettingProgressColor($record)),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Review')
                    ->url(fn (EducationCandidate $record): string => VettingResource::getUrl('edit', ['record' => $record])),
            ])
            ->defaultSort('created_at');
    }

    protected static function vettingProgressLabel(EducationCandidate $record): string
    {
        if ($record->compliance_completed_at) {
            return 'Complete';
        }

        $totalSteps = count(static::STEP_LABELS);
        $stepNumber = min($record->compliance_step ?? 1, $totalSteps);

        return "Step {$stepNumber} of {$totalSteps}: ".static::STEP_LABELS[$stepNumber - 1];
    }

    protected static function vettingProgressColor(EducationCandidate $record): string
    {
        if ($record->compliance_completed_at) {
            return 'success';
        }

        $stepNumber = min($record->compliance_step ?? 1, count(static::STEP_LABELS));

        return match (true) {
            $stepNumber <= 3 => 'danger',
            $stepNumber <= 6 => 'warning',
            default => 'info',
        };
    }
}
