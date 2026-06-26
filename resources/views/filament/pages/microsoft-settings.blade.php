<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6 flex justify-end gap-x-3">
                {{ ($this->getFormActions()[0]) }}
            </div>
        </form>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
