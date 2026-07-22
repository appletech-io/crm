<?php

namespace App\Filament\Resources\CandidateStatuses\Pages;

use App\Filament\Resources\CandidateStatuses\CandidateStatusResource;
use App\Models\CandidateStatus;
use App\Models\CandidateStatusAutomation;
use App\Models\Industry;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                            ->map(fn (array $condition): string => self::conditionLabel($condition, $suggestions))
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

        $fieldOptions = collect($suggestions)
            ->map(fn (array $meta): string => $meta['label'])
            ->all();

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

            Repeater::make('conditions')
                ->label('Conditions')
                ->helperText('All conditions must be true on the candidate before this automation triggers.')
                ->schema([
                    Select::make('field')
                        ->label('Field')
                        ->options($fieldOptions)
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('operator', 'filled');
                            $set('value', null);
                        })
                        ->columnSpan(2),

                    Select::make('operator')
                        ->label('Condition')
                        ->options(fn (Get $get): array => self::operatorOptionsFor($suggestions[$get('field')]['type'] ?? 'string'))
                        ->default('filled')
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('value', null))
                        ->columnSpan(1),

                    Select::make('value')
                        ->label('Value')
                        ->options(['1' => 'True', '0' => 'False'])
                        ->visible(fn (Get $get): bool => self::valueKind($suggestions, $get('field'), $get('operator')) === 'boolean')
                        ->required(fn (Get $get): bool => self::valueKind($suggestions, $get('field'), $get('operator')) === 'boolean')
                        ->columnSpan(2),

                    DatePicker::make('value')
                        ->label('Value')
                        ->visible(fn (Get $get): bool => self::valueKind($suggestions, $get('field'), $get('operator')) === 'date')
                        ->required(fn (Get $get): bool => self::valueKind($suggestions, $get('field'), $get('operator')) === 'date')
                        ->columnSpan(2),

                    TextInput::make('value')
                        ->label('Value (days)')
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Get $get): bool => self::valueKind($suggestions, $get('field'), $get('operator')) === 'days')
                        ->required(fn (Get $get): bool => self::valueKind($suggestions, $get('field'), $get('operator')) === 'days')
                        ->columnSpan(2),

                    TextInput::make('value')
                        ->label('Value')
                        ->visible(fn (Get $get): bool => self::valueKind($suggestions, $get('field'), $get('operator')) === 'text')
                        ->required(fn (Get $get): bool => self::valueKind($suggestions, $get('field'), $get('operator')) === 'text')
                        ->columnSpan(2),
                ])
                ->columns(5)
                ->defaultItems(0)
                ->addActionLabel('Add condition')
                ->itemLabel(fn (array $state): ?string => filled($state['field'] ?? null) ? self::conditionLabel($state, $suggestions) : null)
                ->required()
                ->minItems(1)
                ->columnSpanFull(),
        ];
    }

    /** @return array<string, string> */
    private static function operatorOptionsFor(string $type): array
    {
        return match ($type) {
            'boolean' => [
                'filled' => 'Is filled',
                'equals' => 'Equals',
                'not_equals' => 'Does not equal',
            ],
            'date', 'datetime' => [
                'filled' => 'Is filled',
                'equals' => 'Equals',
                'not_equals' => 'Does not equal',
                'before' => 'Before',
                'after' => 'After',
                'days_since_at_least' => 'At least X days ago',
            ],
            'relation_exists' => [
                'filled' => 'Is filled',
            ],
            default => [
                'filled' => 'Is filled',
                'equals' => 'Equals',
                'not_equals' => 'Does not equal',
                'contains' => 'Contains',
            ],
        };
    }

    /**
     * Which kind of value input a given field+operator combination needs, so the
     * right one of the repeater's conditionally-visible "value" fields is shown.
     *
     * @param  array<string, array{label: string, type: string}>  $suggestions
     */
    private static function valueKind(array $suggestions, ?string $field, ?string $operator): ?string
    {
        if (blank($operator) || $operator === 'filled') {
            return null;
        }

        if ($operator === 'days_since_at_least') {
            return 'days';
        }

        $type = $suggestions[$field]['type'] ?? 'string';

        if ($type === 'boolean' && in_array($operator, ['equals', 'not_equals'], true)) {
            return 'boolean';
        }

        if (in_array($type, ['date', 'datetime'], true) && in_array($operator, ['equals', 'not_equals', 'before', 'after'], true)) {
            return 'date';
        }

        if (in_array($operator, ['equals', 'not_equals', 'contains'], true)) {
            return 'text';
        }

        return null;
    }

    /**
     * @param  array{field?: string, operator?: string, value?: string|null}  $condition
     * @param  array<string, array{label: string, type: string}>  $suggestions
     */
    private static function conditionLabel(array $condition, array $suggestions): string
    {
        $field = $condition['field'] ?? '';
        $label = $suggestions[$field]['label'] ?? $field;
        $operator = $condition['operator'] ?? 'filled';
        $value = self::displayValue($suggestions[$field]['type'] ?? 'string', $condition['value'] ?? null);

        return match ($operator) {
            'equals' => "{$label} = {$value}",
            'not_equals' => "{$label} ≠ {$value}",
            'contains' => "{$label} contains \"{$value}\"",
            'before' => "{$label} before {$value}",
            'after' => "{$label} after {$value}",
            'days_since_at_least' => "{$label} at least {$value} days ago",
            default => "{$label} is filled",
        };
    }

    private static function displayValue(string $type, ?string $value): string
    {
        if ($type === 'boolean') {
            return $value === '1' ? 'True' : 'False';
        }

        return (string) $value;
    }
}
