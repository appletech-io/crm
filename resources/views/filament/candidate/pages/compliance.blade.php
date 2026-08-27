<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <flux:button type="submit" variant="primary">Save</flux:button>
        </div>
    </form>
</x-filament-panels::page>
