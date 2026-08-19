<x-filament-panels::page>
    @if ($this->candidateModelClass())
        @include('filament.pages.analytics.partials.stats-row', ['stats' => $this->stats()])

        {{ $this->table }}
    @else
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Select a sector to view candidate analytics.
            </p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
