<x-filament-panels::page>
    <x-filament::tabs label="Sections">
        <x-filament::tabs.item
            :active="$activeSection === 'search'"
            wire:click="$set('activeSection', 'search')"
            icon="heroicon-o-magnifying-glass"
        >
            Search
        </x-filament::tabs.item>

        <x-filament::tabs.item
            :active="$activeSection === 'all'"
            wire:click="$set('activeSection', 'all')"
            icon="heroicon-o-user-group"
        >
            All Candidates
        </x-filament::tabs.item>
    </x-filament::tabs>

    @if ($activeSection === 'search')
        <form wire:submit="search" class="flex flex-col gap-4">
            {{ $this->form }}

            <div class="flex justify-end">
                <x-filament::button type="submit">
                    Search
                </x-filament::button>
            </div>
        </form>
    @endif

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
