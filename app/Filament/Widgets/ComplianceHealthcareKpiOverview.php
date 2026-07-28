<?php

namespace App\Filament\Widgets;

use App\Models\HealthcareCandidate;

class ComplianceHealthcareKpiOverview extends ComplianceKpiOverview
{
    protected function candidateModelClass(): string
    {
        return HealthcareCandidate::class;
    }
}
