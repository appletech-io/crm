<x-auth-header
    :title="__('Employment History')"
    :description="__('Tell us about your previous jobs, most recent first.')"
/>

<form wire:submit="submitEmploymentHistory" class="mt-3 flex flex-col gap-6">

    <flux:error name="employmentHistories" />

    @foreach ($employmentHistories as $index => $job)
        <div wire:key="employment-history-{{ $index }}" class="flex flex-col gap-4 rounded-lg border border-zinc-200 p-4 dark:border-white/10">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                    {{ __('Job :number', ['number' => $index + 1]) }}
                </p>

                <div class="flex items-center gap-2">
                    <flux:button type="button" size="sm" variant="ghost" wire:click="toggleEmploymentHistoryCollapsed({{ $index }})">
                        {{ ($job['collapsed'] ?? false) ? __('Expand') : __('Collapse') }}
                    </flux:button>

                    <flux:button type="button" size="sm" variant="danger" wire:click="removeEmploymentHistory({{ $index }})">
                        {{ __('Remove') }}
                    </flux:button>
                </div>
            </div>

            @if ($job['collapsed'] ?? false)
                <button
                    type="button"
                    wire:click="toggleEmploymentHistoryCollapsed({{ $index }})"
                    class="flex flex-col items-start gap-0.5 text-left"
                >
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ trim(($job['job_title'] ?: '').' at '.($job['company_name'] ?: ''), ' at ') ?: __('Untitled job') }}
                    </span>

                    @if ($period = $this->workPeriodLabel($job))
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $period }}</span>
                    @endif
                </button>
            @else
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="employmentHistories.{{ $index }}.job_title" :label="__('Job Title')" placeholder="{{ __('Class Teacher') }}" />
                    <flux:input wire:model="employmentHistories.{{ $index }}.company_name" :label="__('Company / School')" placeholder="{{ __('Oakwood Primary School') }}" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input
                        type="date"
                        wire:model.live="employmentHistories.{{ $index }}.worked_from"
                        :label="__('Worked From')"
                        max="{{ now()->format('Y-m-d') }}"
                    />

                    <flux:input
                        type="date"
                        wire:model.live="employmentHistories.{{ $index }}.worked_to"
                        :label="__('Worked To')"
                        max="{{ now()->format('Y-m-d') }}"
                    />
                </div>

                <flux:button type="button" variant="primary" wire:click="saveEmploymentHistory({{ $index }})" class="self-start">
                    {{ __('Add job') }}
                </flux:button>
            @endif
        </div>
    @endforeach

    <flux:button type="button" variant="ghost" wire:click="addEmploymentHistory" class="self-start">
        {{ __('Add another job') }}
    </flux:button>

    <flux:button type="submit" variant="primary" class="w-full">
        {{ __('Continue') }}
    </flux:button>

</form>
