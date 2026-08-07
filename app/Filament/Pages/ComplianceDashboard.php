<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Dashboards\DashboardInterface;
use App\Filament\Pages\Dashboards\NoSectorDashboard;
use Filament\Pages\Dashboard as BaseDashboard;

class ComplianceDashboard extends BaseDashboard
{
    protected static string $routePath = 'compliance-dashboard';

    protected static ?string $navigationLabel = 'Compliance Dashboard';

    protected static ?string $title = 'Compliance Dashboard';

    protected ?DashboardInterface $dashboard = null;

    public function __construct()
    {
        $industry = active_industry();

        if (! $industry) {
            $this->dashboard = new NoSectorDashboard;
        } else {
            $dashboardClass = 'App\\Filament\\Pages\\Dashboards\\Compliance'.ucfirst($industry).'Dashboard';

            if (class_exists($dashboardClass)) {
                $dashboard = app($dashboardClass);

                if ($dashboard instanceof DashboardInterface) {
                    $this->dashboard = $dashboard;
                }
            }
        }
    }

    /**
     * A regular Dashboard nav item already exists for every user (it shows
     * the compliance view itself for compliance-only users) — this second,
     * always-compliance nav item exists purely so admins can reach it
     * without losing access to their own consultant-facing dashboard.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getActiveIndustry(): ?string
    {
        return active_industry();
    }

    public function getTitle(): string
    {
        return $this->dashboard?->getTitle() ?? 'Compliance Dashboard';
    }

    public function getWidgets(): array
    {
        return $this->dashboard?->getWidgets() ?? [];
    }

    /** @return int | array<string, ?int> */
    public function getColumns(): int|array
    {
        return $this->dashboard?->getColumns() ?? 2;
    }
}
