<?php

namespace App\Filament\Resources\CandidateStatuses\Pages;

use App\Filament\Resources\CandidateStatuses\CandidateStatusResource;
use App\Filament\Support\ConditionsRepeaterField;
use App\Models\CandidateStatus;
use App\Models\CandidateStatusAutomation;
use App\Models\Industry;
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

class ManageCandidateStatusAutomations extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CandidateStatusResource::class;

    protected static ?string $title = 'Status Automations';

    protected string $view = 'filament.resources.candidate-statuses.pages.manage-candidate-status-automations';

    public function table(Table $table): Table
    {
        $suggestions = $this->fieldSuggestions();

        return $table
            ->query(
                CandidateStatusAutomation::query()
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
                    ->state(function (CandidateStatusAutomation $record) use ($suggestions): array {
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
                    ->fillForm(fn (CandidateStatusAutomation $record): array => [
                        'candidate_status_id' => $record->candidate_status_id,
                        'to_candidate_status_id' => $record->to_candidate_status_id,
                        'conditions' => $record->conditions,
                    ])
                    ->schema($this->automationFormSchema())
                    ->action(function (CandidateStatusAutomation $record, array $data): void {
                        $record->update([
                            'candidate_status_id' => $data['candidate_status_id'],
                            'to_candidate_status_id' => $data['to_candidate_status_id'] ?? null,
                            'conditions' => $data['conditions'],
                        ]);
                    }),

                Action::make('delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (CandidateStatusAutomation $record) => $record->delete()),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to statuses')
                ->url(CandidateStatusResource::getUrl('index'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray'),

            Action::make('create')
                ->label('New automation')
                ->icon('heroicon-o-plus')
                ->schema($this->automationFormSchema())
                ->action(function (array $data): void {
                    CandidateStatusAutomation::updateOrCreate(
                        [
                            'candidate_status_id' => $data['candidate_status_id'],
                            'to_candidate_status_id' => $data['to_candidate_status_id'] ?? null,
                        ],
                        ['conditions' => $data['conditions']],
                    );
                }),
        ];
    }

    /** @return array<string, array{label: string, type: string}> */
    private function fieldSuggestions(): array
    {
        return Industry::query()->find(active_industry_id())?->candidateFieldSuggestions() ?? [];
    }

    /** @return list<Select|Repeater> */
    private function automationFormSchema(): array
    {
        $statuses = CandidateStatus::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        $suggestions = $this->fieldSuggestions();

        return [
            Select::make('candidate_status_id')
                ->label('From status')
                ->options($statuses)
                ->searchable()
                ->required()
                ->columnSpanFull(),

            Select::make('to_candidate_status_id')
                ->label('To status')
                ->options($statuses)
                ->searchable()
                ->required()
                ->columnSpanFull(),

            ConditionsRepeaterField::make('conditions', fn (): array => $suggestions),
        ];
    }
}
