<div wire:loading.remove wire:target="parseCv">

    <x-auth-header
        :title="__('Upload Your CV')"
        :description="__('Upload your CV as a PDF and we\'ll pre-fill your details automatically.')"
    />

    <form wire:submit="parseCv" class="mt-6 flex flex-col gap-6">

        @if ($application->cv_temp_path && ! $cv)
            <div class="flex items-center gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                <svg class="size-6 shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('CV uploaded') }}</p>
                    <p class="truncate text-xs text-zinc-500">{{ basename($application->cv_temp_path) }}</p>
                </div>
            </div>
        @endif

        <div class="flex flex-col gap-2">
            <label for="cv" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                {{ $application->cv_temp_path ? __('Replace CV / Resume') : __('CV / Resume') }}
                @unless ($application->cv_temp_path)
                    <span class="text-red-500">*</span>
                @endunless
            </label>

            <div class="relative flex items-center justify-center rounded-lg border-2 border-dashed border-zinc-300 bg-zinc-50 px-6 py-10 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="text-center">
                    <svg class="mx-auto size-10 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        @if ($cv)
                            <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $cv->getClientOriginalName() }}</span>
                        @else
                            <span>{{ __('Click to select or drag and drop') }}</span>
                        @endif
                    </p>
                    <p class="mt-1 text-xs text-zinc-500">{{ __('PDF up to 10MB') }}</p>
                    <input
                        id="cv"
                        type="file"
                        wire:model="cv"
                        accept=".pdf"
                        class="absolute inset-0 cursor-pointer opacity-0"
                    />
                </div>
            </div>

            @error('cv')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="parseCv">
            <span wire:loading.remove wire:target="parseCv">
                @if ($cv)
                    {{ __('Analyse CV') }}
                @elseif ($application->cv_temp_path)
                    {{ __('Next') }}
                @else
                    {{ __('Analyse CV') }}
                @endif
            </span>
            <span wire:loading wire:target="parseCv">{{ __('Analysing…') }}</span>
        </flux:button>

    </form>
</div>
