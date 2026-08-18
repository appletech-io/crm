<?php

namespace App\Filament\Resources\QualificationJobTitles\Tables;

use App\Models\JobTitle;
use App\Models\Qualification;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class QualificationJobTitlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('jobTitles'))
            ->columns([
                TextColumn::make('name')
                    ->label('Qualification')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('jobTitles.name')
                    ->label('Allowed Job Titles')
                    ->badge()
                    ->placeholder('None configured'),
            ])
            ->recordActions([
                static::manageAllowedJobTitlesAction(),
            ]);
    }

    private static function manageAllowedJobTitlesAction(): Action
    {
        return Action::make('manageAllowedJobTitles')
            ->label('Manage Allowed Job Titles')
            ->modalHeading(fn (Qualification $record): string => "Allowed Job Titles — {$record->name}")
            ->modalSubmitActionLabel('Save')
            ->fillForm(fn (Qualification $record): array => [
                'job_title_ids' => $record->jobTitles()->pluck('job_titles.id')->all(),
            ])
            ->schema([
                Select::make('job_title_ids')
                    ->label('Allowed Job Titles')
                    ->options(fn (): array => JobTitle::query()
                        ->where('company_id', Auth::user()->company_id)
                        ->where('industry_id', active_industry_id())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                    )
                    ->multiple()
                    ->searchable()
                    ->preload(),
            ])
            ->action(function (array $data, Qualification $record): void {
                $record->jobTitles()->sync(
                    collect($data['job_title_ids'])
                        ->mapWithKeys(fn (int|string $jobTitleId): array => [$jobTitleId => [
                            'company_id' => Auth::user()->company_id,
                            'industry_id' => active_industry_id(),
                        ]])
                        ->all()
                );
            });
    }
}
