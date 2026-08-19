<x-filament-panels::page>
    @include('filament.pages.analytics.partials.stats-row', ['stats' => $this->stats()])

    {{ $this->table }}
</x-filament-panels::page>
