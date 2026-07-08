<x-auth-header
    :title="__('Document Requirements')"
    :description="__('A few quick questions so we know which documents to ask you for.')"
/>

<form wire:submit="saveDocumentRequirements" class="mt-6 flex flex-col gap-6">

    <div class="flex flex-col gap-2">
        <flux:radio.group
            wire:model="right_to_work_type"
            variant="segmented"
            :label="__('What proof of your right to work in the UK can you provide?')"
        >
            <flux:radio value="birth_certificate" label="{{ __('Birth Certificate (UK)') }}" />
            <flux:radio value="passport" label="{{ __('Passport (UK)') }}" />
            <flux:radio value="visa" label="{{ __('Visa') }}" />
        </flux:radio.group>

        @error('right_to_work_type')
            <flux:error>{{ $message }}</flux:error>
        @enderror
    </div>

    <div x-show="$wire.right_to_work_type === 'visa'">
        <flux:input
            wire:model="visa_share_code"
            :label="__('Visa Share Code')"
            placeholder="A1B2C3D4"
        />

        @error('visa_share_code')
            <flux:error>{{ $message }}</flux:error>
        @enderror
    </div>

    <div class="flex flex-col gap-2">
        <flux:radio.group
            wire:model="has_dbs"
            variant="segmented"
            :label="__('Do you currently have a DBS certificate or update service check?')"
        >
            <flux:radio value="yes" label="{{ __('Yes') }}" />
            <flux:radio value="no" label="{{ __('No') }}" />
        </flux:radio.group>

        @error('has_dbs')
            <flux:error>{{ $message }}</flux:error>
        @enderror
    </div>

    <div class="flex flex-col gap-2">
        <flux:radio.group
            wire:model="has_naric"
            variant="segmented"
            :label="__('Do you have a UK NARIC statement of comparability? (optional)')"
        >
            <flux:radio value="yes" label="{{ __('Yes') }}" />
            <flux:radio value="no" label="{{ __('No') }}" />
        </flux:radio.group>

        @error('has_naric')
            <flux:error>{{ $message }}</flux:error>
        @enderror
    </div>

    <flux:button type="submit" variant="primary" class="w-full">
        {{ __('Next') }}
    </flux:button>

</form>
