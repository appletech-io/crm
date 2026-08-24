<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->integrations() as $integration)
            <a
                href="{{ $integration['url'] }}"
                class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 transition hover:ring-primary-500 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-400"
            >
                <div class="flex items-center justify-between">
                    <p class="text-base font-semibold text-gray-950 dark:text-white">{{ $integration['provider']->label() }}</p>

                    @if ($integration['connected'])
                        <x-filament::badge color="success">Connected</x-filament::badge>
                    @else
                        <x-filament::badge color="gray">Not connected</x-filament::badge>
                    @endif
                </div>
            </a>
        @endforeach
    </div>
</x-filament-panels::page>
