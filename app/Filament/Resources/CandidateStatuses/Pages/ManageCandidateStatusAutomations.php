<?php

namespace App\Filament\Resources\CandidateStatuses\Pages;

use App\Filament\Resources\CandidateStatuses\CandidateStatusResource;
use App\Models\CandidateStatus;
use App\Models\CandidateStatusAutomation;
use App\Models\Industry;
use Filament\Actions\Action;
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

                TextColumn::make('completed_fields')
                    ->label('Required fields')
                    ->state(function (CandidateStatusAutomation $record): array {
                        $fields = $record->completed_fields ?? [];

                        if (count($fields) <= 6) {
                            return $fields;
                        }

                        return [...array_slice($fields, 0, 6), '+'.(count($fields) - 6).' more'];
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
                        'completed_fields' => $record->completed_fields,
                    ])
                    ->schema($this->automationFormSchema())
                    ->action(function (CandidateStatusAutomation $record, array $data): void {
                        $record->update([
                            'candidate_status_id' => $data['candidate_status_id'],
                            'to_candidate_status_id' => $data['to_candidate_status_id'] ?? null,
                            'completed_fields' => $data['completed_fields'],
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
                        ['completed_fields' => $data['completed_fields']],
                    );
                }),
        ];
    }

    /** @return list<Select> */
    private function automationFormSchema(): array
    {
        $statuses = CandidateStatus::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        $suggestions = Industry::query()->find(active_industry_id())?->candidateFieldSuggestions() ?? [];

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

            Select::make('completed_fields')
                ->label('Required fields')
                ->helperText('All fields must be filled on the candidate before this automation triggers.')
                ->options(array_combine($suggestions, $suggestions))
                ->multiple()
                ->searchable()
                ->required()
                ->columnSpanFull(),
        ];
    }
}
