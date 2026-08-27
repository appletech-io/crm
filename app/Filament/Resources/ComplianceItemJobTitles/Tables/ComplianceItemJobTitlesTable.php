<?php

namespace App\Filament\Resources\ComplianceItemJobTitles\Tables;

use App\Models\ComplianceItem;
use App\Models\JobTitle;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ComplianceItemJobTitlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('jobTitles'))
            ->columns([
                TextColumn::make('name')
                    ->label('Compliance Item')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('jobTitles.name')
                    ->label('Required For Job Titles')
                    ->badge()
                    ->placeholder('None configured'),
            ])
            ->recordActions([
                static::manageRequiredJobTitlesAction(),
            ]);
    }

    private static function manageRequiredJobTitlesAction(): Action
    {
        return Action::make('manageRequiredJobTitles')
            ->label('Manage Required Job Titles')
            ->modalHeading(fn (ComplianceItem $record): string => "Required Job Titles — {$record->name}")
            ->modalSubmitActionLabel('Save')
            ->fillForm(fn (ComplianceItem $record): array => [
                'job_title_ids' => $record->jobTitles()->pluck('job_titles.id')->all(),
            ])
            ->schema([
                Select::make('job_title_ids')
                    ->label('Required Job Titles')
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
            ->action(function (array $data, ComplianceItem $record): void {
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
