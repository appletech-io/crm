<div class="flex flex-col gap-3">
    <div class="flex items-center justify-end text-sm">
        <span class="text-zinc-500 dark:text-zinc-400">
            {{ __('Step :current of :total', ['current' => $currentStep, 'total' => $this->totalSteps]) }} &middot; {{ $this->progressPercentage }}%
        </span>
    </div>

    <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
        <div class="h-full rounded-full bg-[var(--color-accent)] transition-all duration-300" style="width: {{ $this->progressPercentage }}%"></div>
    </div>

    <div class="flex items-center justify-between">
        <flux:button
            type="button"
            icon="chevron-left"
            square
            size="sm"
            variant="ghost"
            aria-label="{{ __('Back') }}"
            wire:click="viewStep({{ $currentStep - 1 }})"
            :disabled="$currentStep <= 1"
        />

        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
            {{ $this->stepLabels[$currentStep] ?? '' }}
        </span>

        <flux:button
            type="button"
            icon="chevron-right"
            square
            size="sm"
            variant="ghost"
            aria-label="{{ __('Forward') }}"
            wire:click="viewStep({{ $currentStep + 1 }})"
            :disabled="$currentStep >= $this->application->current_step"
        />
    </div>
</div>
