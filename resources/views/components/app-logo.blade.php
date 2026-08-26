@props([
    'sidebar' => false,
])

@if($sidebar)
    <a href="{{ route('dashboard') }}" wire:navigate>
        <img
            src="{{ asset('images/appletech.png') }}"
            alt="{{ config('app.name') }}"
            class="w-36 h-auto object-contain"
        />
    </a>
@else
    <flux:brand {{ $attributes }}>
        <x-slot name="logo">
            <img
                src="{{ asset('images/appletech.png') }}"
                alt="{{ config('app.name') }}"
                class="w-36 h-auto object-contain"
            />
        </x-slot>
    </flux:brand>
@endif
