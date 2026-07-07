<x-auth-header
    :title="__('Create Your Account')"
    :description="__('Set a password so you can log in and track your application.')"
/>

<form wire:submit="completeApplication" class="mt-6 flex flex-col gap-6">

    <flux:input
        wire:model="password"
        type="password"
        viewable
        :label="__('Password')"
    />

    @error('password')
        <flux:error>{{ $message }}</flux:error>
    @enderror

    <flux:input
        wire:model="password_confirmation"
        type="password"
        viewable
        :label="__('Confirm Password')"
    />

    <flux:button type="submit" variant="primary" class="w-full">
        {{ __('Complete Application') }}
    </flux:button>

</form>
