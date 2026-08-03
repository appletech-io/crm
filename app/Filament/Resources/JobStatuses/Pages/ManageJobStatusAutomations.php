<?php

namespace App\Filament\Resources\JobStatuses\Pages;

use App\Filament\Resources\JobStatuses\JobStatusResource;
use App\Filament\Support\ConditionsRepeaterField;
use App\Models\JobStatus;
use App\Models\JobStatusAutomation;
use App\Models\Vacancy;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ManageJobStatusAutomations extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = JobStatusResource::class;

    protected static ?string $title = 'Status Automations';

    protected string $view = 'filament.resources.job-statuses.pages.manage-job-status-automations';

    public function table(Table $table): Table
    {
        $suggestions = Vacancy::candidateFieldSuggestions();

        return $table
            ->query(
                JobStatusAutomation::query()
                    ->whereHas('fromStatus', fn (Builder $q) => $q
                        ->where('company_id', Auth::user()->company_id)
                        ->where('industry_id', active_industry_id())
                    )
                    ->with(['fromStatus', 'toStatus'])
            )
            ->columns([
                TextColumn::make('fromStatus.name')
                    ->label('From status')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('toStatus.name')
                    ->label('To status')
                    ->badge()
                    ->color('info'),

                TextColumn::make('conditions')
                    ->label('Conditions')
                    ->state(function (JobStatusAutomation $record) use ($suggestions): array {
                        $labels = collect($record->conditions ?? [])
                            ->map(fn (array $condition): string => ConditionsRepeaterField::conditionLabel($condition, $suggestions))
                            ->values()
                            ->all();

                        if (count($labels) <= 6) {
                            return $labels;
                        }

                        return [...array_slice($labels, 0, 6), '+'.(count($labels) - 6).' more'];
                    })
                    ->badge()
                    ->color(fn (string $state): string => str_starts_with($state, '+') ? 'gray' : 'success'),
            ])
            ->recordActions([
                Action::make('edit')
                    ->icon('heroicon-o-pencil')
                    ->fillForm(fn (JobStatusAutomation $record): array => [
                        'job_status_id' => $record->job_status_id,
                        'to_job_status_id' => $record->to_job_status_id,
                        'conditions' => $record->conditions,
                    ])
                    ->schema($this->automationFormSchema())
                    ->action(function (JobStatusAutomation $record, array $data): void {
                        $record->update([
                            'job_status_id' => $data['job_status_id'],
                            'to_job_status_id' => $data['to_job_status_id'] ?? null,
                            'conditions' => $data['conditions'],
                        ]);
                    }),

                Action::make('delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (JobStatusAutomation $record) => $record->delete()),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to statuses')
                ->url(JobStatusResource::getUrl('index'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray'),

            Action::make('create')
                ->label('New automation')
                ->icon('heroicon-o-plus')
                ->schema($this->automationFormSchema())
                ->action(function (array $data): void {
                    JobStatusAutomation::updateOrCreate(
                        [
                            'job_status_id' => $data['job_status_id'],
                            'to_job_status_id' => $data['to_job_status_id'] ?? null,
                        ],
                        ['conditions' => $data['conditions']],
                    );
                }),
        ];
    }

    /** @return list<Select|Repeater> */
    private function automationFormSchema(): array
    {
        $statuses = JobStatus::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        $suggestions = Vacancy::candidateFieldSuggestions();

        return [
            Select::make('job_status_id')
                ->label('From status')
                ->options($statuses)
                ->searchable()
                ->required()
                ->columnSpanFull(),

            Select::make('to_job_status_id')
                ->label('To status')
                ->options($statuses)
                ->searchable()
                ->required()
                ->columnSpanFull(),

            ConditionsRepeaterField::make('conditions', fn (): array => $suggestions),
        ];
    }
}
