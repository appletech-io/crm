<x-filament-panels::page>
    <div class="flex flex-col gap-6">
        {{ $this->form }}

        @include('filament.resources.education-candidates.pages.partials.skills-picker')

        <div class="flex justify-end">
            <x-filament::button wire:click="processCvUploads">
                Upload &amp; Process
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
