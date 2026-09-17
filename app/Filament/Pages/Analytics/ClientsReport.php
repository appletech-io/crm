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
        return auth()->user()?->hasAnyRole(['admin', 'consultant']) ?? false;
    }

    /** @return array<string, int|string> */
    public function stats(): array
    {
        $stats = ['Clients active' => $this->rows()->count()];

        if ($this->includeBookingStats()) {
            $bookingTotals = BookingRevenuePeriodCalculator::totals($this->periodStart(), $this->periodEnd(), $this->filterConsultantId());
            $stats['Booking revenue'] = '£'.number_format($bookingTotals['revenue'], 2);
            $stats['Booking margin'] = '£'.number_format($bookingTotals['margin'], 2);
        }

        if ($this->includePlacementStats()) {
            $placementTotals = PlacementPeriodCalculator::totals($this->periodStart(), $this->periodEnd(), $this->filterConsultantId());
            $stats['Placements'] = $placementTotals['count'];
            $stats['Placement value'] = '£'.number_format($placementTotals['value'], 2);
        }

        return $stats;
    }

    private function includeBookingStats(): bool
    {
        return active_industry_uses_bookings();
    }

    private function includePlacementStats(): bool
    {
        return active_industry_uses_perm();
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
                TextColumn::make('bookings')->label('Bookings')->alignEnd()->visible($this->includeBookingStats()),
                TextColumn::make('revenue')->label('Revenue')->formatStateUsing(fn (float $state): string => '£'.number_format($state, 2))->alignEnd()->visible($this->includeBookingStats()),
                TextColumn::make('margin')->label('Margin')->formatStateUsing(fn (float $state): string => '£'.number_format($state, 2))->alignEnd()->weight('bold')->visible($this->includeBookingStats()),
                TextColumn::make('placements')->label('Placements')->alignEnd()->visible($this->includePlacementStats()),
                TextColumn::make('placementValue')->label('Placement Value')->formatStateUsing(fn (float $state): string => '£'.number_format($state, 2))->alignEnd()->visible($this->includePlacementStats()),
                TextColumn::make('activeVacancies')->label('Open Vacancies')->alignEnd()->visible($this->includePlacementStats()),
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
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
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

        $revenueRows = $this->includeBookingStats()
            ? BookingRevenuePeriodCalculator::byClient($start, $end, $consultantId)->keyBy('clientId')
            : collect();

        $placementRows = $this->includePlacementStats()
            ? PlacementPeriodCalculator::byClient($start, $end, $consultantId)->keyBy('clientId')
            : collect();

        $clientIds = $revenueRows->keys()->merge($placementRows->keys())->unique()->values();

        $clients = Client::query()
            ->whereIn('id', $clientIds)
            ->with('consultant')
            ->when(
                $this->includePlacementStats(),
                fn (Builder $query) => $query->withCount(['vacancies as active_vacancies_count' => fn (Builder $q) => $q->whereHas('jobStatus', fn (Builder $q) => $q->where('is_filled_status', false))])
            )
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

    /**
     * A non-admin only ever sees their own figures here — the Consultant
     * filter is hidden from them, so this ignores whatever's in table
     * filter state and forces their own id instead.
     */
    private function filterConsultantId(): ?int
    {
        $user = auth()->user();

        if ($user && ! $user->isAdmin()) {
            return $user->id;
        }

        $value = $this->getTableFilterState('consultant_id')['value'] ?? null;

        return $value ? (int) $value : null;
    }
}
