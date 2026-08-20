<?php

namespace App\Filament\Pages\Analytics;

use App\Enums\VacancyEmploymentType;
use App\Models\Client;
use App\Models\JobStatus;
use App\Models\User;
use App\Models\Vacancy;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class VacanciesReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.analytics.vacancies-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $navigationLabel = 'Vacancies & Placements';

    protected static \UnitEnum|string|null $navigationGroup = 'Analytics';

    protected static ?string $title = 'Vacancies & Placements';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /** @return array<string, int|string> */
    public function stats(): array
    {
        /** @var Builder<Vacancy>|null $query */
        $query = $this->getFilteredTableQuery();

        if (! $query) {
            return ['Total vacancies' => 0, 'Open' => 0, 'Filled' => 0, 'Est. pipeline value' => '£0.00', 'Actual margin' => '£0.00'];
        }

        $total = (clone $query)->count();
        $filled = (clone $query)->whereHas('jobStatus', fn (Builder $q) => $q->where('is_filled_status', true))->count();
        $open = $total - $filled;
        $pipelineValue = (clone $query)
            ->whereHas('jobStatus', fn (Builder $q) => $q->where('is_filled_status', false))
            ->get()
            ->sum(fn (Vacancy $vacancy): float => $vacancy->estimatedPlacementValue() ?? 0);
        // Not filtered to is_filled_status vacancies: actualPlacementValue()
        // already sums only what's actually been placed, so a vacancy with
        // e.g. 2 of 3 positions placed still contributes its real margin
        // here even though it hasn't moved into a filled status yet.
        $actualMargin = (clone $query)
            ->with('placements')
            ->get()
            ->sum(fn (Vacancy $vacancy): float => $vacancy->actualPlacementValue() ?? 0);

        return [
            'Total vacancies' => $total,
            'Open' => $open,
            'Filled' => $filled,
            'Est. pipeline value' => '£'.number_format($pipelineValue, 2),
            'Actual margin' => '£'.number_format($actualMargin, 2),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Vacancy::query()->forActiveIndustry()->with(['client', 'jobTitle', 'jobStatus', 'consultant']))
            ->columns([
                TextColumn::make('jobTitle.name')->label('Role')->searchable()->sortable(),
                TextColumn::make('client.name')->label('Client')->searchable()->sortable(),
                TextColumn::make('consultant.name')->label('Consultant')->searchable()->sortable(),
                TextColumn::make('jobStatus.name')->label('Status')->badge()->color(fn (Vacancy $record): ?string => $record->jobStatus?->color),
                TextColumn::make('employment_type')->label('Type')->badge()->formatStateUsing(fn (VacancyEmploymentType $state): string => $state->label())->color(fn (VacancyEmploymentType $state): string => $state === VacancyEmploymentType::Temp ? 'warning' : 'gray'),
                TextColumn::make('positions_available')->label('Positions')->alignEnd()->sortable(),
                TextColumn::make('salary_min')->label('Salary min')->formatStateUsing(fn (?float $state): string => $state !== null ? '£'.number_format($state, 2) : '—')->alignEnd()->sortable(),
                TextColumn::make('salary_max')->label('Salary max')->formatStateUsing(fn (?float $state): string => $state !== null ? '£'.number_format($state, 2) : '—')->alignEnd()->sortable(),
                TextColumn::make('estimated_value')->label('Est. Value')->state(fn (Vacancy $record): ?float => $record->estimatedPlacementValue())->formatStateUsing(fn (?float $state): string => $state !== null ? '£'.number_format($state, 2) : '—')->alignEnd(),
                TextColumn::make('actual_value')->label('Actual Margin')->state(fn (Vacancy $record): ?float => $record->actualPlacementValue())->formatStateUsing(fn (?float $state): string => $state !== null ? '£'.number_format($state, 2) : '—')->alignEnd(),
                TextColumn::make('days_open')->label('Days open')->state(fn (Vacancy $record): int => (int) $record->created_at->diffInDays($record->filled_at ?? now()))->alignEnd(),
                TextColumn::make('filled_at')->label('Filled')->date()->sortable(),
                IconColumn::make('open_for_applications')->label('Open for applications')->boolean(),
            ])
            ->filters([
                Filter::make('created')
                    ->schema([
                        DatePicker::make('from')->label('From')->default(now()->startOfMonth()->toDateString())->native(false),
                        DatePicker::make('until')->label('To')->default(now()->toDateString())->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('created_at', '>=', $from))
                            ->when($data['until'] ?? null, fn (Builder $q, string $until) => $q->whereDate('created_at', '<=', $until));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = Indicator::make('From '.Carbon::parse($data['from'])->toFormattedDateString())->removeField('from');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = Indicator::make('To '.Carbon::parse($data['until'])->toFormattedDateString())->removeField('until');
                        }

                        return $indicators;
                    }),
                SelectFilter::make('employment_type')
                    ->label('Type')
                    ->placeholder('All Types')
                    ->options(VacancyEmploymentType::options()),
                SelectFilter::make('job_status_id')
                    ->label('Status')
                    ->placeholder('All Statuses')
                    ->options(fn (): array => JobStatus::query()
                        ->where('industry_id', active_industry_id())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                    ),
                TernaryFilter::make('is_filled')
                    ->label('Placement status')
                    ->placeholder('Open & filled')
                    ->trueLabel('Filled only')
                    ->falseLabel('Open only')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('jobStatus', fn (Builder $q) => $q->where('is_filled_status', true)),
                        false: fn (Builder $query) => $query->whereHas('jobStatus', fn (Builder $q) => $q->where('is_filled_status', false)),
                        blank: fn (Builder $query) => $query,
                    ),
                SelectFilter::make('consultant_id')
                    ->label('Consultant')
                    ->placeholder('All Consultants')
                    ->options(fn (): array => User::role('consultant')
                        ->whereHas('industries', fn ($query) => $query->where('industries.id', active_industry_id()))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                    ),
                SelectFilter::make('client_id')
                    ->label('Client')
                    ->placeholder('All Clients')
                    ->options(fn (): array => Client::query()
                        ->where('industry_id', active_industry_id())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                    ),
            ], layout: FiltersLayout::AboveContent)
            ->defaultSort('created_at', 'desc');
    }
}
