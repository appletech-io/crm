<x-auth-header
    :title="__('Employment Conduct')"
    :description="__('Please answer the following questions honestly and in confidence.')"
/>

<form wire:submit="saveEmploymentConduct" class="mt-6 flex flex-col gap-6">

    <div class="flex flex-col gap-2">
        <flux:radio.group
            wire:model="retired_early"
            variant="segmented"
            :label="__('Have you retired earlier than expected?')"
        >
            <flux:radio value="yes" label="{{ __('Yes') }}" />
            <flux:radio value="no" label="{{ __('No') }}" />
        </flux:radio.group>

        @error('retired_early')
            <flux:error>{{ $message }}</flux:error>
        @enderror
    </div>

    <div x-show="$wire.retired_early === 'yes'" class="flex flex-col gap-2">
        <flux:radio.group
            wire:model="retired_early_medical_grounds"
            variant="segmented"
            :label="__('If you have answered YES to the above question, was this on medical grounds?')"
        >
            <flux:radio value="yes" label="{{ __('Yes') }}" />
            <flux:radio value="no" label="{{ __('No') }}" />
        </flux:radio.group>

        @error('retired_early_medical_grounds')
            <flux:error>{{ $message }}</flux:error>
        @enderror
    </div>

    <div class="flex flex-col gap-2">
        <flux:radio.group
            wire:model="dismissed_from_relevant_position"
            variant="segmented"
            :label="__('Have you ever been fired from a position that is relevant?')"
        >
            <flux:radio value="yes" label="{{ __('Yes') }}" />
            <flux:radio value="no" label="{{ __('No') }}" />
        </flux:radio.group>

        @error('dismissed_from_relevant_position')
            <flux:error>{{ $message }}</flux:error>
        @enderror
    </div>

    <div x-show="$wire.dismissed_from_relevant_position === 'yes'">
        <flux:textarea
            wire:model="dismissal_details"
            :label="__('Please specify')"
            rows="4"
        />

        @error('dismissal_details')
            <flux:error>{{ $message }}</flux:error>
        @enderror
    </div>

    <div class="flex flex-col gap-2">
        <flux:radio.group
            wire:model="subject_to_disciplinary_action"
            variant="segmented"
            :label="__('Has anyone ever brought disciplinary action against you?')"
        >
            <flux:radio value="yes" label="{{ __('Yes') }}" />
            <flux:radio value="no" label="{{ __('No') }}" />
        </flux:radio.group>

        @error('subject_to_disciplinary_action')
            <flux:error>{{ $message }}</flux:error>
        @enderror
    </div>

    <div x-show="$wire.subject_to_disciplinary_action === 'yes'">
        <flux:textarea
            wire:model="disciplinary_action_details"
            :label="__('Please specify')"
            rows="4"
        />

        @error('disciplinary_action_details')
            <flux:error>{{ $message }}</flux:error>
        @enderror
    </div>

    <flux:button type="submit" variant="primary" class="w-full">
        {{ __('Next') }}
    </flux:button>

</form>
