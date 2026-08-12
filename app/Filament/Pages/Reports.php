<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Reports\NoSectorReports;
use App\Filament\Pages\Reports\ReportsInterface;
use App\Models\Client;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Reports extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'reports';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Reports';

    protected static \UnitEnum|string|null $navigationGroup = 'Admin';

    protected static ?string $title = 'Reports';

    protected ?ReportsInterface $reports = null;

    public function __construct()
    {
        $industry = active_industry();

        if (! $industry) {
            $this->reports = new NoSectorReports;
        } else {
            $reportsClass = 'App\\Filament\\Pages\\Reports\\'.ucfirst($industry).'Reports';

            if (class_exists($reportsClass)) {
                $reports = app($reportsClass);

                if ($reports instanceof ReportsInterface) {
                    $this->reports = $reports;
                }
            }
        }
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getTitle(): string
    {
        return $this->reports?->getTitle() ?? 'Reports';
    }

    public function getWidgets(): array
    {
        return $this->reports?->getWidgets() ?? [];
    }

    /** @return int | array<string, ?int> */
    public function getColumns(): int|array
    {
        return $this->reports?->getColumns() ?? 2;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('start_date')
                ->label('From')
                ->default(now()->startOfMonth()->toDateString())
                ->native(false),
            DatePicker::make('end_date')
                ->label('To')
                ->default(now()->toDateString())
                ->native(false),
            Select::make('consultant_id')
                ->label('Consultant')
                ->placeholder('All Consultants')
                ->options(fn (): array => User::role('consultant')
                    ->whereHas('industries', fn ($query) => $query->where('industries.id', active_industry_id()))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray()
                )
                ->searchable(),
            Select::make('client_id')
                ->label('Client')
                ->placeholder('All Clients')
                ->options(fn (): array => Client::query()
                    ->where('industry_id', active_industry_id())
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray()
                )
                ->searchable(),
        ]);
    }
}
