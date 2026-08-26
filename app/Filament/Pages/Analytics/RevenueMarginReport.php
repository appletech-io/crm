<?php

namespace App\Filament\Pages\Analytics;

use App\Models\Client;
use App\Models\User;
use App\Services\Reporting\BookingRevenuePeriodCalculator;
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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RevenueMarginReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.analytics.revenue-margin-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Revenue & Margin';

    protected static \UnitEnum|string|null $navigationGroup = 'Analytics';

    protected static ?string $title = 'Revenue & Margin';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    /** @return array<string, int|string|float|null> */
    public function stats(): array
    {
        $totals = BookingRevenuePeriodCalculator::totals(
            $this->periodStart(),
            $this->periodEnd(),
            $this->filterConsultantId(),
            $this->filterClientId(),
        );

        return [
            'Bookings' => $totals['bookings'],
            'Revenue' => '£'.number_format($totals['revenue'], 2),
            'Cost' => '£'.number_format($totals['cost'], 2),
            'Margin' => '£'.number_format($totals['margin'], 2),
            'Avg Margin %' => number_format($totals['avgMargin'] * 100, 1).'%',
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
                TextColumn::make('jobTitle')->label('Role'),
                TextColumn::make('days')->label('Days worked')->alignEnd(),
                TextColumn::make('revenue')->formatStateUsing(fn (float $state): string => '£'.number_format($state, 2))->alignEnd(),
                TextColumn::make('cost')->formatStateUsing(fn (float $state): string => '£'.number_format($state, 2))->alignEnd(),
                TextColumn::make('margin')->formatStateUsing(fn (float $state): string => '£'.number_format($state, 2))->alignEnd()->weight('bold'),
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
                SelectFilter::make('client_id')
                    ->label('Client')
                    ->placeholder('All Clients')
                    ->options(fn (): array => Client::query()
                        ->where('industry_id', active_industry_id())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                    ),
            ], layout: FiltersLayout::AboveContent);
    }

    /** @return Collection<int, array{bookingId: int, clientName: string, consultantName: string, jobTitle: string, revenue: float, cost: float, margin: float, days: int}> */
    private function rows(): Collection
    {
        return BookingRevenuePeriodCalculator::byBooking(
            $this->periodStart(),
            $this->periodEnd(),
            $this->filterConsultantId(),
            $this->filterClientId(),
        );
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

    private function filterClientId(): ?int
    {
        $value = $this->getTableFilterState('client_id')['value'] ?? null;

        return $value ? (int) $value : null;
    }
}
