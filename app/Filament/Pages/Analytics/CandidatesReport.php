<?php

namespace App\Filament\Pages\Analytics;

use App\Models\CandidateStatus;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\User;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CandidatesReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.analytics.candidates-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Candidates';

    protected static \UnitEnum|string|null $navigationGroup = 'Analytics';

    protected static ?string $title = 'Candidates';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    /** @return class-string<Model>|null */
    public function candidateModelClass(): ?string
    {
        $industry = active_industry();

        return $industry ? Industry::candidateModelForSlug($industry) : null;
    }

    private function candidateModelSupportsBookings(): bool
    {
        $modelClass = $this->candidateModelClass();

        return $modelClass && method_exists($modelClass, 'bookings');
    }

    /** @return array<string, int|string> */
    public function stats(): array
    {
        $query = $this->getFilteredTableQuery();

        if (! $query) {
            return ['Candidates' => 0, 'Placed' => 0, 'Placement rate' => '0%'];
        }

        $total = (clone $query)->count();
        $placed = $this->candidateModelSupportsBookings() ? (clone $query)->has('bookings')->count() : 0;

        return [
            'Candidates' => $total,
            'Placed' => $placed,
            'Placement rate' => $total > 0 ? number_format(($placed / $total) * 100, 1).'%' : '0%',
        ];
    }

    public function table(Table $table): Table
    {
        /** @var class-string<Model>|null $modelClass */
        $modelClass = $this->candidateModelClass();
        $supportsBookings = $this->candidateModelSupportsBookings();

        $query = $modelClass
            ? $modelClass::query()->visibleToCurrentUser()
            : EducationCandidate::query()->whereRaw('1 = 0');

        $query->with(['consultant', 'latestStatus.status']);

        if ($supportsBookings) {
            $query->withCount('bookings');
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('first_name')->label('First name')->searchable()->sortable(),
                TextColumn::make('last_name')->label('Last name')->searchable()->sortable(),
                TextColumn::make('consultant.name')->label('Consultant')->searchable()->sortable(),
                TextColumn::make('latestStatus.status.name')->label('Current status')->badge()->color(fn ($record): ?string => $record->latestStatus?->status?->color),
                TextColumn::make('created_at')->label('Registered')->date()->sortable(),
                TextColumn::make('bookings_count')->label('Bookings')->alignEnd()->sortable()->visible($supportsBookings),
                IconColumn::make('is_placed')->label('Placed')->state(fn ($record): bool => $record->bookings_count > 0)->boolean()->visible($supportsBookings),
            ])
            ->filters([
                Filter::make('registered')
                    ->schema([
                        DatePicker::make('from')->label('From')->native(false),
                        DatePicker::make('until')->label('To')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('created_at', '>=', $from))
                            ->when($data['until'] ?? null, fn (Builder $q, string $until) => $q->whereDate('created_at', '<=', $until));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = Indicator::make('Registered from '.Carbon::parse($data['from'])->toFormattedDateString())->removeField('from');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = Indicator::make('Registered until '.Carbon::parse($data['until'])->toFormattedDateString())->removeField('until');
                        }

                        return $indicators;
                    }),
                SelectFilter::make('consultant_id')
                    ->label('Consultant')
                    ->placeholder('All Consultants')
                    ->options(fn (): array => User::role('consultant')
                        ->whereHas('industries', fn ($query) => $query->where('industries.id', active_industry_id()))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                    ),
                SelectFilter::make('current_status')
                    ->label('Current status')
                    ->placeholder('All Statuses')
                    ->options(fn (): array => CandidateStatus::query()
                        ->where('industry_id', active_industry_id())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                    )
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $q, string $statusId) => $q->whereHas('latestStatus', fn (Builder $q2) => $q2->where('candidate_status_id', $statusId)),
                        );
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->defaultSort('created_at', 'desc');
    }
}
