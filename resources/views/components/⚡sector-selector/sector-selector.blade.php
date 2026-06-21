<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Select Your Sector')"
        :description="__('You have access to multiple sectors. Please select which one you would like to work in.')"
    />

    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:select
            wire:model="industry_slug"
            :label="__('Sector')"
            :placeholder="__('Select a sector...')"
            required
        >
            @foreach ($industries as $slug => $name)
                <flux:select.option value="{{ $slug }}">{{ $name }}</flux:select.option>
            @endforeach
        </flux:select>

        @error('industry_slug')
        <flux:error>{{ $message }}</flux:error>
        @enderror

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('Continue') }}
        </flux:button>
    </form>
</div>
