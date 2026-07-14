<x-filament-panels::page>
    <x-filament::tabs label="Sections">
        <x-filament::tabs.item
            :active="$activeSection === 'weekly'"
            wire:click="$set('activeSection', 'weekly')"
            icon="heroicon-o-calendar-days"
        >
            Weekly View
        </x-filament::tabs.item>

        <x-filament::tabs.item
            :active="$activeSection === 'all'"
            wire:click="$set('activeSection', 'all')"
            icon="heroicon-o-table-cells"
        >
            All
        </x-filament::tabs.item>
    </x-filament::tabs>

    @if ($activeSection === 'weekly')
        @livewire(\App\Filament\Resources\EducationBookings\Widgets\WeeklyBookingsByClient::class)
    @else
        {{ $this->table }}
    @endif
</x-filament-panels::page>
