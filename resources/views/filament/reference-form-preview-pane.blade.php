<div class="flex flex-col gap-3 lg:sticky lg:top-8">
    <div class="flex items-center justify-between gap-2">
        <span class="text-sm font-semibold text-gray-950 dark:text-white">
            {{ __('Preview') }}
        </span>

        <a
            href="{{ route('reference-forms.preview', $referenceForm) }}"
            target="_blank"
            rel="noopener"
            class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
        >
            {{ __('Open in new tab') }}
        </a>
    </div>

    {{--
        The referee-facing page is built from Flux components, whose styles
        aren't part of the Filament panel's stylesheet — rendering it inline
        here would show the right questions in the wrong clothes. An iframe
        of the real page is the only faithful option.

        wire:ignore keeps Livewire from touching the frame on re-render,
        which would reload it and lose scroll position on every keystroke;
        the page inside polls for the draft and updates itself instead.
    --}}
    <div
        wire:ignore
        class="overflow-hidden rounded-xl bg-white ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
    >
        <iframe
            src="{{ route('reference-forms.preview', ['referenceForm' => $referenceForm, 'draft' => 1]) }}"
            title="{{ __('Reference form preview') }}"
            class="h-[42rem] w-full"
        ></iframe>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        {{ __('Updates as you edit — no need to save first.') }}
    </p>
</div>
