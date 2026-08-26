<?php

namespace App\Filament\Pages\Analytics;

use App\Models\Client;
use App\Models\User;
use App\Services\Reporting\BookingRevenuePeriodCalculator;
use App\Services\Reporting\PlacementPeriodCalculator;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ClientsReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.analytics.clients-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $navigationLabel = 'Clients';

    protected static \UnitEnum|string|null $navigationGroup = 'Analytics';

    protected static ?string $title = 'Clients';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    /** @return array<string, int|string> */
    public function stats(): array
    {
        $bookingTotals = BookingRevenuePeriodCalculator::totals($this->periodStart(), $this->periodEnd(), $this->filterConsultantId());
        $placementTotals = PlacementPeriodCalculator::totals($this->periodStart(), $this->periodEnd(), $this->filterConsultantId());

        return [
            'Clients active' => $this->rows()->count(),
            'Booking revenue' => '£'.number_format($bookingTotals['revenue'], 2),
            'Booking margin' => '£'.number_format($bookingTotals['margin'], 2),
            'Placements' => $placementTotals['count'],
            'Placement value' => '£'.number_format($placementTotals['value'], 2),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int $page, int $recordsPerPage): LengthAwarePaginator {
                $rows = $this->rows();

                return new LengthAwarePaginator(
                    $rows->forPage($page, $recordsPerPage)->values(),
                    $rows->count(),
                    $recordsPerPage,
                    $page,
                );
            })
            ->columns([
                TextColumn::make('clientName')->label('Client'),
                TextColumn::make('consultantName')->label('Consultant'),
                TextColumn::make('bookings')->label('Bookings')->alignEnd(),
                TextColumn::make('revenue')->label('Revenue')->formatStateUsing(fn (float $state): string => '£'.number_format($state, 2))->alignEnd(),
                TextColumn::make('margin')->label('Margin')->formatStateUsing(fn (float $state): string => '£'.number_format($state, 2))->alignEnd()->weight('bold'),
                TextColumn::make('placements')->label('Placements')->alignEnd(),
                TextColumn::make('placementValue')->label('Placement Value')->formatStateUsing(fn (float $state): string => '£'.number_format($state, 2))->alignEnd(),
                TextColumn::make('activeVacancies')->label('Open Vacancies')->alignEnd(),
            ])
            ->filters([
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label('From')->default(now()->startOfMonth()->toDateString())->native(false),
                        DatePicker::make('until')->label('To')->default(now()->toDateString())->native(false),
                    ])
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
                SelectFilter::make('consultant_id')
                    ->label('Consultant')
                    ->placeholder('All Consultants')
                    ->options(fn (): array => User::role('consultant')
                        ->whereHas('industries', fn ($query) => $query->where('industries.id', active_industry_id()))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                    ),
            ], layout: FiltersLayout::AboveContent);
    }

    /** @return Collection<int, array{clientId: int, clientName: string, consultantName: string, bookings: int, revenue: float, cost: float, margin: float, placements: int, placementValue: float, activeVacancies: int}> */
    private function rows(): Collection
    {
        $start = $this->periodStart();
        $end = $this->periodEnd();
        $consultantId = $this->filterConsultantId();

        $revenueRows = BookingRevenuePeriodCalculator::byClient($start, $end, $consultantId)->keyBy('clientId');
        $placementRows = PlacementPeriodCalculator::byClient($start, $end, $consultantId)->keyBy('clientId');

        $clientIds = $revenueRows->keys()->merge($placementRows->keys())->unique()->values();

        $clients = Client::query()
            ->whereIn('id', $clientIds)
            ->with('consultant')
            ->withCount(['vacancies as active_vacancies_count' => fn (Builder $query) => $query->whereHas('jobStatus', fn (Builder $q) => $q->where('is_filled_status', false))])
            ->get()
            ->keyBy('id');

        return $clientIds
            ->map(function (int $clientId) use ($revenueRows, $placementRows, $clients): array {
                $revenue = $revenueRows->get($clientId);
                $placement = $placementRows->get($clientId);
                $client = $clients->get($clientId);

                return [
                    'clientId' => $clientId,
                    'clientName' => $client?->name ?? $revenue['clientName'] ?? 'Unknown client',
                    'consultantName' => $client?->consultant?->name ?? 'Unassigned',
                    'bookings' => $revenue['bookings'] ?? 0,
                    'revenue' => $revenue['revenue'] ?? 0.0,
                    'cost' => $revenue['cost'] ?? 0.0,
                    'margin' => $revenue['margin'] ?? 0.0,
                    'placements' => $placement['count'] ?? 0,
                    'placementValue' => $placement['value'] ?? 0.0,
                    'activeVacancies' => $client?->active_vacancies_count ?? 0,
                ];
            })
            ->sortByDesc('margin')
            ->values();
    }

    private function periodStart(): Carbon
    {
        return Carbon::parse($this->getTableFilterState('period')['from'] ?? now()->startOfMonth()->toDateString());
    }

    private function periodEnd(): Carbon
    {
        return Carbon::parse($this->getTableFilterState('period')['until'] ?? now()->toDateString());
    }

    private function filterConsultantId(): ?int
    {
        $value = $this->getTableFilterState('consultant_id')['value'] ?? null;

        return $value ? (int) $value : null;
    }
}
