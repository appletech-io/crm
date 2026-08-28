<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasCandidateAvailabilityCalendar;
use App\Models\Candidate;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use Filament\Widgets\Widget;

class CandidateAvailabilityCalendar extends Widget
{
    use HasCandidateAvailabilityCalendar;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.candidate-availability-calendar';

    public EducationCandidate|HealthcareCandidate|Candidate|null $record = null;

    public function mount(EducationCandidate|HealthcareCandidate|Candidate|null $record = null): void
    {
        $this->record = $record;
        $this->initializeAvailabilityMonth();
    }

    protected function availabilityCandidate(): EducationCandidate|HealthcareCandidate|Candidate|null
    {
        return $this->record;
    }
}
