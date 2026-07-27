<x-filament-panels::page>
    <form wire:submit="search" class="flex flex-col gap-4">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit">
                Search
            </x-filament::button>
        </div>
    </form>

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
