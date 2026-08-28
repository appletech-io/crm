@php
    $weeks = $this->availabilityMonthWeeks();
@endphp

<div class="flex flex-col gap-4">
    <div class="flex items-center justify-between gap-3">
        <p class="text-base font-semibold text-gray-950 dark:text-white">
            {{ $this->availabilityMonthLabel() }}
        </p>

        <div class="flex items-center gap-2">
            <x-filament::icon-button icon="heroicon-o-chevron-left" wire:click="goToPreviousMonth" label="Previous month" />
            <x-filament::button color="gray" size="sm" wire:click="goToCurrentMonth">This Month</x-filament::button>
            <x-filament::icon-button icon="heroicon-o-chevron-right" wire:click="goToNextMonth" label="Next month" />
        </div>
    </div>

    @if (empty($weeks))
        <p class="text-sm text-gray-500 dark:text-gray-400">No candidate record to show availability for.</p>
    @else
        <div class="grid grid-cols-7 gap-1 text-center text-xs text-gray-400 dark:text-gray-500">
            <span>Mon</span>
            <span>Tue</span>
            <span>Wed</span>
            <span>Thu</span>
            <span>Fri</span>
            <span>Sat</span>
            <span>Sun</span>
        </div>

        @foreach ($weeks as $week)
            <div class="grid grid-cols-7 gap-1">
                @foreach ($week as $day)
                    @if ($day === null)
                        <div class="h-14"></div>
                    @else
                        @php
                            $isSelected = in_array($day['date'], $this->selectedDates, true);
                            $ring = $isSelected ? 'ring-2 ring-primary-500' : '';
                        @endphp

                        @if ($day['editable'])
                            <button
                                type="button"
                                wire:click="toggleDaySelection('{{ $day['date'] }}')"
                                wire:key="availability-day-{{ $day['date'] }}"
                                class="{{ $ring }} {{ $this->availabilityStatusClasses($day['status']) }} flex h-14 flex-col items-center justify-center gap-0.5 rounded-lg text-xs transition"
                            >
                                <span>{{ \Illuminate\Support\Carbon::parse($day['date'])->day }}</span>
                                <span class="text-[10px] font-medium">{{ $this->availabilityStatusLabel($day['status']) }}</span>
                            </button>
                        @else
                            <div
                                wire:key="availability-day-{{ $day['date'] }}"
                                class="{{ $this->availabilityStatusClasses($day['status']) }} flex h-14 flex-col items-center justify-center gap-0.5 rounded-lg text-xs"
                            >
                                <span>{{ \Illuminate\Support\Carbon::parse($day['date'])->day }}</span>
                                <span class="text-[10px] font-medium">{{ $this->availabilityStatusLabel($day['status']) }}</span>
                            </div>
                        @endif
                    @endif
                @endforeach
            </div>
        @endforeach

        <div class="flex flex-wrap items-center gap-3 border-t border-gray-100 pt-3 text-xs dark:border-white/10">
            <span class="flex items-center gap-1">
                <span class="h-3 w-3 rounded bg-primary-100 dark:bg-primary-500/20"></span> Full
            </span>
            <span class="flex items-center gap-1">
                <span class="h-3 w-3 rounded bg-blue-100 dark:bg-blue-500/20"></span> AM
            </span>
            <span class="flex items-center gap-1">
                <span class="h-3 w-3 rounded bg-purple-100 dark:bg-purple-500/20"></span> PM
            </span>
            <span class="flex items-center gap-1">
                <span class="h-3 w-3 rounded bg-red-100 dark:bg-red-500/20"></span> Not Available
            </span>
            <span class="flex items-center gap-1">
                <span class="h-3 w-3 rounded bg-gray-200 dark:bg-white/10"></span> Booked
            </span>
            <span class="ml-auto text-gray-500 dark:text-gray-400">
                {{ count($this->selectedDates) }} day(s) selected
            </span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-filament::button color="gray" size="sm" wire:click="selectAllDays">Select all</x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="clearSelection">Clear selection</x-filament::button>

            <span class="mx-1 h-4 w-px bg-gray-200 dark:bg-white/10"></span>

            <x-filament::button color="primary" size="sm" :disabled="empty($this->selectedDates)" wire:click="applyAvailabilityStatus('available')">Set Full</x-filament::button>
            <x-filament::button color="info" size="sm" :disabled="empty($this->selectedDates)" wire:click="applyAvailabilityStatus('available_am')">Set AM</x-filament::button>
            <x-filament::button color="info" size="sm" :disabled="empty($this->selectedDates)" wire:click="applyAvailabilityStatus('available_pm')">Set PM</x-filament::button>
            <x-filament::button color="danger" size="sm" :disabled="empty($this->selectedDates)" wire:click="applyAvailabilityStatus('not_available')">Set Not Available</x-filament::button>
            <x-filament::button color="gray" size="sm" :disabled="empty($this->selectedDates)" wire:click="applyAvailabilityStatus(null)">Clear</x-filament::button>
        </div>
    @endif
</div>
