<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Dashboards\DashboardInterface;
use App\Filament\Pages\Dashboards\NoSectorDashboard;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Dashboard';

    protected ?DashboardInterface $dashboard = null;

    public function __construct()
    {
        $industry = active_industry();

        if (! $industry) {
            $this->dashboard = new NoSectorDashboard;
        } else {
            $prefix = auth()->user()?->isComplianceOnly() ? 'Compliance' : '';
            $dashboardClass = 'App\\Filament\\Pages\\Dashboards\\'.$prefix.ucfirst($industry).'Dashboard';

            if (class_exists($dashboardClass)) {
                $dashboard = app($dashboardClass);
                if ($dashboard instanceof DashboardInterface) {
                    $this->dashboard = $dashboard;
                }
            }
        }
    }

    public static function canAccess(): bool
    {
        return true;
    }

    public function getActiveIndustry(): ?string
    {
        return active_industry();
    }

    public function getTitle(): string
    {
        if ($this->dashboard) {
            return $this->dashboard->getTitle();
        }

        return 'Dashboard';
    }

    public function getWidgets(): array
    {
        if ($this->dashboard) {
            return $this->dashboard->getWidgets();
        }

        return [];
    }

    /** @return int | array<string, ?int> */
    public function getColumns(): int|array
    {
        return $this->dashboard?->getColumns() ?? 2;
    }
}
