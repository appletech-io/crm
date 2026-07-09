<x-auth-header
    :title="__('Medical Information')"
    :description="__('Please answer the following questions honestly and in confidence.')"
/>

<form wire:submit="saveMedicalInformation" class="mt-6 flex flex-col gap-6">

    <div class="flex flex-col gap-3 rounded-lg bg-zinc-50 p-4 text-sm text-zinc-600 dark:bg-white/5 dark:text-zinc-400">
        <p>{{ __('The Education Health Standards (England) Regulations 2003 states that a person shall not be appointed to relevant employment if, having regard to any duty of the employer under the Equality Act 2010, does not have the health and mental and physical capacity for that employment.') }}</p>

        <p>{{ __('The following questions on health and disability are asked in order to find out your needs in terms of reasonable adjustment to access our recruitment service and to find out your needs in order to perform the job or position sought.') }}</p>
    </div>

    <div class="flex flex-col gap-2">
        <flux:radio.group
            wire:model="has_health_condition_or_disability"
            variant="segmented"
            :label="__('Do you now have any health conditions or disabilities that would make it difficult for you to perform the duties of the position you want?')"
        >
            <flux:radio value="yes" label="{{ __('Yes') }}" />
            <flux:radio value="no" label="{{ __('No') }}" />
        </flux:radio.group>

        @error('has_health_condition_or_disability')
            <flux:error>{{ $message }}</flux:error>
        @enderror
    </div>

    <div x-show="$wire.has_health_condition_or_disability === 'yes'">
        <flux:textarea
            wire:model="health_condition_details"
            :label="__('Please specify')"
            rows="4"
        />

        @error('health_condition_details')
            <flux:error>{{ $message }}</flux:error>
        @enderror
    </div>

    <flux:textarea
        wire:model="reasonable_accommodations"
        :label="__('What reasonable accommodations do you require, if you have a disability, in order to access the hiring process and attend the interview?')"
        rows="4"
    />

    <div class="flex flex-col gap-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Emergency Contact') }}</p>

        <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-2">
                <flux:input
                    wire:model="emergency_contact_name"
                    :label="__('Name')"
                    placeholder="Jane Smith"
                    required
                />

                @error('emergency_contact_name')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>

            <div class="flex flex-col gap-2">
                <flux:input
                    wire:model="emergency_contact_number"
                    type="tel"
                    :label="__('Phone Number')"
                    placeholder="+44 7700 900000"
                    required
                />

                @error('emergency_contact_number')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>
        </div>
    </div>

    <flux:button type="submit" variant="primary" class="w-full">
        {{ __('Next') }}
    </flux:button>

</form>
