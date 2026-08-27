<?php

namespace App\Filament\EducationCandidate\Pages;

use App\Filament\Concerns\HasCandidateAvailabilityTable;
use App\Models\Candidate;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class Availability extends Page implements HasTable
{
    use HasCandidateAvailabilityTable;
    use InteractsWithTable;

    protected string $view = 'filament.candidate.pages.availability';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Availability';

    protected static ?string $title = 'Availability';

    public function mount(): void
    {
        $this->initializeAvailabilityWeek();
    }

    protected function availabilityCandidate(): EducationCandidate|HealthcareCandidate|Candidate|null
    {
        /** @var EducationCandidate|HealthcareCandidate|Candidate|null $candidate */
        $candidate = auth()->user()->candidate;

        return $candidate;
    }

    public function table(Table $table): Table
    {
        return $this->configureAvailabilityTable($table);
    }
}
