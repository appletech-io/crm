<x-auth-header
    :title="__('Your Details')"
    :description="__('Review and complete your personal information below.')"
/>

<form wire:submit="savePersonalDetails" class="mt-6 flex flex-col gap-8">

    <div class="flex flex-col gap-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Personal Information') }}</p>

        <div class="grid grid-cols-2 gap-4">
            <flux:select wire:model="title" :label="__('Title')" placeholder="{{ __('Select…') }}">
                @foreach (['Mr', 'Mrs', 'Miss', 'Ms', 'Dr', 'Prof'] as $t)
                    <flux:select.option value="{{ $t }}">{{ $t }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="first_name" :label="__('First Name')" placeholder="John" required />
        </div>

        <flux:input wire:model="last_name" :label="__('Last Name')" placeholder="Smith" required />

        <div class="grid grid-cols-2 gap-4">
            <flux:input wire:model="phone" type="tel" :label="__('Phone')" placeholder="+44 20 7946 0000" />
            <flux:input wire:model="mobile" type="tel" :label="__('Mobile')" placeholder="+44 7700 900000" />
        </div>
    </div>

    <div class="flex flex-col gap-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Address') }}</p>

        <flux:input wire:model="address" :label="__('Address')" placeholder="123 Example Street" />

        <div class="grid grid-cols-2 gap-4">
            <flux:input wire:model="city" :label="__('City / Town')" placeholder="London" />
            <flux:input wire:model="postcode" :label="__('Postcode')" placeholder="SW1A 1AA" />
        </div>
    </div>

    @foreach(['first_name', 'last_name'] as $field)
        @error($field)
            <flux:error>{{ $message }}</flux:error>
        @enderror
    @endforeach

    <flux:button type="submit" variant="primary" class="w-full">
        {{ __('Next') }}
    </flux:button>

</form>
