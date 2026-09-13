<?php

namespace App\Filament\Widgets;

use App\Models\Candidate;

class ComplianceGenericKpiOverview extends ComplianceKpiOverview
{
    protected function candidateModelClass(): string
    {
        return Candidate::class;
    }
}
