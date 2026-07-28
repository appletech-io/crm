<?php

namespace App\Filament\Widgets;

use App\Models\EducationCandidate;

class ComplianceEducationKpiOverview extends ComplianceKpiOverview
{
    protected function candidateModelClass(): string
    {
        return EducationCandidate::class;
    }
}
