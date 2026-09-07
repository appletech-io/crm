@if (active_industry() !== null)
    <div x-data="{ open: false }" class="fixed right-6 bottom-6 z-50 flex w-96 max-w-[calc(100vw-3rem)] flex-col items-end">
        <div
            x-show="open"
            x-transition
            class="mb-4 h-[32rem] w-full"
        >
            <livewire:ask-assistant :is-popup="true" wire:key="ask-assistant-popup" />
        </div>

        <button
            type="button"
            x-on:click="open = ! open"
            title="{{ __('Ask Assistant') }}"
            class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg transition hover:scale-105 hover:bg-primary-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 focus-visible:ring-offset-2"
        >
            <span class="sr-only">{{ __('Ask Assistant') }}</span>

            <x-filament::icon x-show="! open" icon="heroicon-o-sparkles" class="h-6 w-6" />
            <x-filament::icon x-show="open" icon="heroicon-o-x-mark" class="h-6 w-6" />
        </button>
    </div>
@endif
