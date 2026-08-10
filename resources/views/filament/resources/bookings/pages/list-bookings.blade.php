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
            :active="$activeSection === 'requests'"
            wire:click="$set('activeSection', 'requests')"
            icon="heroicon-o-inbox-arrow-down"
            :badge="$this->requestsCount() ?: null"
            badge-color="warning"
        >
            Requests
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
        @livewire(\App\Filament\Resources\Bookings\Widgets\BookingWeekStats::class)
        @livewire(\App\Filament\Resources\Bookings\Widgets\WeeklyBookingsByClient::class)
    @else
        {{ $this->table }}
    @endif
</x-filament-panels::page>
