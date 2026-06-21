<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Dashboards\DashboardInterface;
use App\Filament\Widgets\NoIndustryWidget;
use App\Models\User;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Dashboard';

    protected ?DashboardInterface $dashboard = null;

    public function __construct()
    {
        $industry = $this->getActiveIndustry();

        $dashboardClass = 'App\\Filament\\Pages\\Dashboards\\'.ucfirst($industry).'Dashboard';

        if (class_exists($dashboardClass)) {
            $dashboard = app($dashboardClass);
            if ($dashboard instanceof DashboardInterface) {
                $this->dashboard = $dashboard;
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

        if (! active_industry()) {
            return [
                NoIndustryWidget::class,
            ];
        }

        return [];
    }
}
