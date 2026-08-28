<?php

namespace App\Filament\Concerns;

use App\Enums\CandidateAvailabilityStatus;
use App\Models\Candidate;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Services\Candidates\CandidateMonthlyAvailability;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

trait HasCandidateAvailabilityCalendar
{
    public string $monthStart;

    /** @var array<int, string> */
    public array $selectedDates = [];

    abstract protected function availabilityCandidate(): EducationCandidate|HealthcareCandidate|Candidate|null;

    protected function initializeAvailabilityMonth(): void
    {
        $this->monthStart = Carbon::now()->startOfMonth()->toDateString();
    }

    public function goToPreviousMonth(): void
    {
        $this->monthStart = Carbon::parse($this->monthStart)->subMonthNoOverflow()->toDateString();
        $this->clearSelection();
    }

    public function goToNextMonth(): void
    {
        $this->monthStart = Carbon::parse($this->monthStart)->addMonthNoOverflow()->toDateString();
        $this->clearSelection();
    }

    public function goToCurrentMonth(): void
    {
        $this->monthStart = Carbon::now()->startOfMonth()->toDateString();
        $this->clearSelection();
    }

    public function toggleDaySelection(string $date): void
    {
        if (! $this->isDateEditable($date)) {
            return;
        }

        $this->selectedDates = in_array($date, $this->selectedDates, true)
            ? array_values(array_diff($this->selectedDates, [$date]))
            : [...$this->selectedDates, $date];
    }

    public function selectAllDays(): void
    {
        $this->selectedDates = collect($this->availabilityMonthDays())
            ->where('editable', true)
            ->pluck('date')
            ->all();
    }

    public function clearSelection(): void
    {
        $this->selectedDates = [];
    }

    /**
     * The actual persistence behind a single day's status — pulled out to a
     * plain public method (rather than left inline) so it can be exercised
     * directly, since Filament doesn't check authorization on custom
     * Livewire actions automatically.
     */
    public function setAvailabilityStatus(string $date, ?string $status): void
    {
        $candidate = $this->availabilityCandidate();

        if (! $candidate || ! $this->isDateEditable($date)) {
            return;
        }

        // A plain where('date', $date) can't be trusted to match: the "date"
        // cast reads back as date-only but still writes a full datetime
        // string, so an exact string comparison against a bare Y-m-d value
        // silently fails to find the existing row.
        $existing = $candidate->availabilities()->whereDate('date', $date)->first();

        if ($status === null) {
            $existing?->delete();

            return;
        }

        if ($existing) {
            $existing->update(['status' => $status]);
        } else {
            $candidate->availabilities()->create(['date' => $date, 'status' => $status]);
        }
    }

    public function applyAvailabilityStatus(?string $status): void
    {
        foreach ($this->selectedDates as $date) {
            $this->setAvailabilityStatus($date, $status);
        }

        $this->clearSelection();
    }

    /**
     * Recomputes the month containing $date rather than trusting whatever
     * month is currently on screen — setAvailabilityStatus() can be called
     * directly (e.g. from a test, or a future API) for a date outside the
     * currently navigated month.
     */
    protected function isDateEditable(string $date): bool
    {
        $candidate = $this->availabilityCandidate();

        if (! $candidate) {
            return false;
        }

        $day = collect(CandidateMonthlyAvailability::forMonth($candidate, Carbon::parse($date)))
            ->firstWhere('date', $date);

        return (bool) ($day['editable'] ?? false);
    }

    /** @return array<int, array<string, mixed>> */
    protected function availabilityMonthDays(): array
    {
        $candidate = $this->availabilityCandidate();

        if (! $candidate) {
            return [];
        }

        return CandidateMonthlyAvailability::forMonth($candidate, Carbon::parse($this->monthStart));
    }

    /**
     * The month's days padded to full Monday–Sunday weeks (leading/trailing
     * cells are null) so the Blade view can render a plain 7-column grid.
     *
     * @return array<int, array<int, array<string, mixed>|null>>
     */
    public function availabilityMonthWeeks(): array
    {
        $days = collect($this->availabilityMonthDays())->keyBy('date');

        if ($days->isEmpty()) {
            return [];
        }

        $firstOfMonth = Carbon::parse($this->monthStart)->startOfMonth();
        $lastOfMonth = $firstOfMonth->copy()->endOfMonth();

        $cells = [];

        for ($i = 1; $i < $firstOfMonth->dayOfWeekIso; $i++) {
            $cells[] = null;
        }

        foreach (CarbonPeriod::create($firstOfMonth, $lastOfMonth) as $date) {
            $cells[] = $days->get($date->toDateString());
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        return array_chunk($cells, 7);
    }

    public function availabilityMonthLabel(): string
    {
        return Carbon::parse($this->monthStart)->format('F Y');
    }

    public function availabilityStatusLabel(?string $status): string
    {
        return match ($status) {
            CandidateAvailabilityStatus::Available->value => 'Full',
            CandidateAvailabilityStatus::AvailableAm->value => 'AM',
            CandidateAvailabilityStatus::AvailablePm->value => 'PM',
            CandidateAvailabilityStatus::NotAvailable->value => 'N/A',
            CandidateAvailabilityStatus::Booked->value => 'Booked',
            default => '',
        };
    }

    public function availabilityStatusClasses(?string $status): string
    {
        return match ($status) {
            CandidateAvailabilityStatus::Available->value => 'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300',
            CandidateAvailabilityStatus::AvailableAm->value => 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300',
            CandidateAvailabilityStatus::AvailablePm->value => 'bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-300',
            CandidateAvailabilityStatus::NotAvailable->value => 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300',
            CandidateAvailabilityStatus::Booked->value => 'bg-gray-200 text-gray-500 dark:bg-white/10 dark:text-gray-400',
            default => 'bg-gray-50 text-gray-400 dark:bg-white/5 dark:text-gray-500',
        };
    }
}
