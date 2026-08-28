<?php

namespace App\Filament\EducationCandidate\Pages;

use App\Filament\Concerns\HasCandidateAvailabilityCalendar;
use App\Models\Candidate;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Availability extends Page
{
    use HasCandidateAvailabilityCalendar;

    protected string $view = 'filament.candidate.pages.availability';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Availability';

    protected static ?string $title = 'Availability';

    public function mount(): void
    {
        $this->initializeAvailabilityMonth();
    }

    protected function availabilityCandidate(): EducationCandidate|HealthcareCandidate|Candidate|null
    {
        /** @var EducationCandidate|HealthcareCandidate|Candidate|null $candidate */
        $candidate = auth()->user()->candidate;

        return $candidate;
    }
}
