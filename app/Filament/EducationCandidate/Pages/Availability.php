<?php

namespace App\Filament\EducationCandidate\Pages;

use App\Filament\Concerns\HasCandidateAvailabilityCalendar;
use App\Models\Candidate;
use App\Models\CompanyIndustry;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
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

    /**
     * A booking-only concept — hidden for a candidate whose sector has
     * Bookings switched off, even though the rest of this portal
     * (Documents/Compliance) stays available regardless, since those still
     * matter for a Perm-only candidate. A candidate-portal login has no
     * active-industry session cache the way a staff login does, so this
     * resolves the candidate's own company/industry directly rather than
     * calling active_industry_uses_bookings().
     */
    public static function canAccess(): bool
    {
        $candidate = auth()->user()?->candidate;

        if (! $candidate) {
            return false;
        }

        $industryId = $candidate instanceof Candidate
            ? $candidate->industry_id
            : Industry::where('slug', Industry::slugForCandidateModel($candidate::class))->value('id');

        return CompanyIndustry::usesBookings($candidate->company_id, $industryId);
    }

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
