<div class="flex flex-col gap-3">
    <div class="flex items-center justify-between text-sm">
        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $this->stepLabels[$currentStep] ?? '' }}</span>
        <span class="text-zinc-500 dark:text-zinc-400">
            {{ __('Step :current of :total', ['current' => $currentStep, 'total' => $this->totalSteps]) }} &middot; {{ $this->progressPercentage }}%
        </span>
    </div>

    <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
        <div class="h-full rounded-full bg-[var(--color-accent)] transition-all duration-300" style="width: {{ $this->progressPercentage }}%"></div>
    </div>

    <div class="flex items-center gap-2">
        @foreach ($this->stepLabels as $step => $label)
            <button
                type="button"
                @if ($step <= $this->application->current_step) wire:click="viewStep({{ $step }})" @else disabled @endif
                @class([
                    'flex-1 rounded-md px-2 py-1.5 text-center text-xs font-medium transition',
                    'bg-[var(--color-accent)] text-[var(--color-accent-foreground)]' => $step === $currentStep,
                    'cursor-pointer text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' => $step !== $currentStep && $step <= $this->application->current_step,
                    'cursor-not-allowed text-zinc-300 dark:text-zinc-600' => $step > $this->application->current_step,
                ])
            >
                {{ $label }}
            </button>
        @endforeach
    </div>
</div>
