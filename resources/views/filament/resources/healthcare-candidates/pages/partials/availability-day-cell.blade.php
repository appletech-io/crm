@php
    $cell = $getState();
    $pendingLabel = match ($cell['pendingStatus'] ?? null) {
        'available' => 'Full',
        'available_am' => 'AM',
        'available_pm' => 'PM',
        default => null,
    };
@endphp

<div
    x-data="{ open: false }"
    @mouseenter="open = true"
    @mouseleave="open = false"
    class="relative flex w-full items-center justify-center py-1"
>
    <button
        type="button"
        title="{{ $pendingLabel !== null ? "Pending: {$pendingLabel} — click Save in the row menu" : $cell['tooltip'] }}"
        @if ($cell['isSelectable'])
            wire:click="handleDayClick({{ $cell['candidateId'] }}, {{ $cell['isoWeekday'] }})"
        @endif
        @class([
            $cell['colorClasses'],
            'relative flex h-6 w-6 items-center justify-center rounded-full transition',
            'cursor-pointer hover:bg-gray-100 dark:hover:bg-white/5' => $cell['isSelectable'],
            'ring-2 ring-primary-500 ring-offset-1 dark:ring-offset-gray-900' => $pendingLabel !== null,
        ])
    >
        <x-filament::icon :icon="$cell['icon']" class="h-5 w-5" />

        @if ($pendingLabel !== null)
            <span class="absolute -top-1.5 -right-1.5 rounded-full bg-primary-600 px-1 text-[9px] font-bold leading-tight text-white">
                {{ $pendingLabel }}
            </span>
        @endif
    </button>

    @if ($cell['status'] === null)
        <div
            x-show="open"
            x-cloak
            x-transition
            class="fi-dropdown-panel absolute top-full left-1/2 z-20 mt-1 flex w-16 -translate-x-1/2 flex-col gap-1 rounded-lg border border-gray-200 bg-white p-1 shadow-lg dark:border-white/10 dark:bg-gray-800"
        >
            @foreach (['available' => 'Full', 'available_am' => 'AM', 'available_pm' => 'PM'] as $value => $label)
                <button
                    type="button"
                    wire:click="stageAvailability({{ $cell['candidateId'] }}, {{ $cell['isoWeekday'] }}, '{{ $value }}')"
                    @class([
                        'rounded px-1.5 py-1 text-[10px] font-medium whitespace-nowrap',
                        'bg-primary-600 text-white' => $cell['pendingStatus'] === $value,
                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10' => $cell['pendingStatus'] !== $value,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>
    @endif
</div>
