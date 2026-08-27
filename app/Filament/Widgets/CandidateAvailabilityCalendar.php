<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasCandidateAvailabilityTable;
use App\Models\Candidate;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class CandidateAvailabilityCalendar extends TableWidget
{
    use HasCandidateAvailabilityTable;

    protected int|string|array $columnSpan = 'full';

    public EducationCandidate|HealthcareCandidate|Candidate|null $record = null;

    public function mount(EducationCandidate|HealthcareCandidate|Candidate|null $record = null): void
    {
        $this->record = $record;
        $this->initializeAvailabilityWeek();
    }

    protected function availabilityCandidate(): EducationCandidate|HealthcareCandidate|Candidate|null
    {
        return $this->record;
    }

    public function table(Table $table): Table
    {
        return $this->configureAvailabilityTable($table);
    }
}
