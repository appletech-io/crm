<?php

use App\Models\CompanionEvent;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.companion', ['title' => 'Manage Events'])] class extends Component
{
    #[Url]
    public string $month = '';

    #[Url]
    public string $selectedDate = '';

    public ?int $editingId = null;

    public string $title = '';

    public ?string $time = null;

    public ?string $notes = null;

    public function mount(): void
    {
        $this->month = $this->month ?: Carbon::today()->format('Y-m');
        $this->selectedDate = $this->selectedDate ?: Carbon::today()->format('Y-m-d');
    }

    #[Computed]
    public function monthStart(): Carbon
    {
        // Force day 1 in the parsed string itself — Carbon::createFromFormat
        // with format 'Y-m' alone fills the missing day-of-month in from
        // *today's* date during parsing, before any method chain runs. On
        // the 29th-31st that can parse straight into a nonexistent date
        // (e.g. "September 31") which PHP silently overflows into the
        // following month right there, so a later ->startOfMonth() is too
        // late to fix it.
        return Carbon::createFromFormat('Y-m-d', $this->month.'-01');
    }

    #[Computed]
    public function calendarDays(): array
    {
        $start = $this->monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $end = $this->monthStart->copy()->endOfMonth()->endOfWeek(Carbon::MONDAY);

        $days = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }

        return $days;
    }

    #[Computed]
    public function eventCountsByDate(): array
    {
        return CompanionEvent::whereBetween('date', [
            $this->monthStart->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d'),
            $this->monthStart->copy()->endOfMonth()->endOfWeek(Carbon::MONDAY)->format('Y-m-d'),
        ])
            ->get()
            ->groupBy(fn ($event) => $event->date->format('Y-m-d'))
            ->map->count()
            ->toArray();
    }

    #[Computed]
    public function selectedDateCarbon(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $this->selectedDate);
    }

    #[Computed]
    public function eventsForSelectedDate()
    {
        return CompanionEvent::whereDate('date', $this->selectedDate)
            ->orderByRaw('time IS NULL, time asc')
            ->get();
    }

    #[Computed]
    public function timeSlots(): array
    {
        return CompanionEvent::timeSlotOptions();
    }

    public function previousMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m-d', $this->month.'-01')->subMonth()->format('Y-m');

        unset($this->monthStart, $this->calendarDays, $this->eventCountsByDate);
    }

    public function nextMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m-d', $this->month.'-01')->addMonth()->format('Y-m');

        unset($this->monthStart, $this->calendarDays, $this->eventCountsByDate);
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
        $this->resetForm();
    }

    public function edit(int $eventId): void
    {
        $event = CompanionEvent::findOrFail($eventId);

        $this->editingId = $event->id;
        $this->title = $event->title;
        $this->time = $event->time?->format('H:i');
        $this->notes = $event->notes;
    }

    public function save(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $data['date'] = $this->selectedDate;

        if ($this->editingId) {
            CompanionEvent::findOrFail($this->editingId)->update($data);
        } else {
            CompanionEvent::create($data);
        }

        Notification::make()->title('Saved')->success()->send();

        unset($this->eventsForSelectedDate, $this->eventCountsByDate);
        $this->resetForm();
    }

    public function delete(int $eventId): void
    {
        CompanionEvent::findOrFail($eventId)->delete();

        Notification::make()->title('Deleted')->success()->send();

        unset($this->eventsForSelectedDate, $this->eventCountsByDate);

        if ($this->editingId === $eventId) {
            $this->resetForm();
        }
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->time = null;
        $this->notes = null;
        $this->resetValidation();
    }
};

?>

<div class="mx-auto flex max-w-3xl flex-col gap-8 px-6 py-10">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Manage Events</flux:heading>
        <flux:button href="{{ route('companion.board') }}" wire:navigate variant="subtle">
            View board
        </flux:button>
    </div>

    {{-- Month --}}
    <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
            <flux:button type="button" wire:click="previousMonth" icon="chevron-left" size="sm" variant="subtle" />
            <flux:heading size="lg">{{ $this->monthStart->format('F Y') }}</flux:heading>
            <flux:button type="button" wire:click="nextMonth" icon="chevron-right" size="sm" variant="subtle" />
        </div>

        <div class="grid grid-cols-7 gap-1 text-center text-xs text-neutral-500">
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $label)
                <div class="py-1">{{ $label }}</div>
            @endforeach
        </div>

        <div class="grid grid-cols-7 gap-1">
            @foreach ($this->calendarDays as $day)
                @php
                    $key = $day->format('Y-m-d');
                    $count = $this->eventCountsByDate[$key] ?? 0;
                    $inMonth = $day->month === $this->monthStart->month;
                    $isSelected = $key === $this->selectedDate;
                @endphp
                <button
                    type="button"
                    wire:click="selectDate('{{ $key }}')"
                    @class([
                        'flex flex-col items-center gap-0.5 rounded-lg py-2 text-sm',
                        'text-neutral-300' => ! $inMonth,
                        'text-neutral-900' => $inMonth && ! $isSelected,
                        'bg-amber-400 text-neutral-900 font-semibold' => $isSelected,
                        'hover:bg-neutral-100' => ! $isSelected,
                    ])
                >
                    {{ $day->day }}
                    @if ($count > 0)
                        <span @class([
                            'h-1.5 w-1.5 rounded-full',
                            'bg-neutral-900' => $isSelected,
                            'bg-amber-500' => ! $isSelected,
                        ])></span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    {{-- Selected day --}}
    <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
        <flux:heading size="lg" class="mb-4">{{ $this->selectedDateCarbon->format('l j F Y') }}</flux:heading>

        @if ($this->eventsForSelectedDate->isEmpty())
            <p class="text-neutral-400">No events yet for this day.</p>
        @else
            <ul class="flex flex-col gap-2">
                @foreach ($this->eventsForSelectedDate as $event)
                    <li class="flex items-center justify-between rounded-lg bg-neutral-50 px-4 py-3">
                        <div>
                            <span class="font-mono text-neutral-500">{{ $event->display_time }}</span>
                            <span class="ml-3 text-neutral-900">{{ $event->title }}</span>
                            @if ($event->notes)
                                <p class="mt-1 text-sm text-neutral-500">{{ $event->notes }}</p>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            <flux:button wire:click="edit({{ $event->id }})" size="sm" variant="subtle">Edit</flux:button>
                            <flux:button
                                wire:click="delete({{ $event->id }})"
                                wire:confirm="Delete this event?"
                                size="sm"
                                variant="danger"
                            >Delete</flux:button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Add / edit form --}}
    <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
        <flux:heading size="lg" class="mb-4">{{ $editingId ? 'Edit event' : 'Add event' }}</flux:heading>

        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:input wire:model="title" label="What" placeholder="e.g. Doctor's appointment" required />

            <flux:select wire:model="time" label="When (optional — leave blank for anytime today)">
                <flux:select.option value="">Anytime</flux:select.option>
                @foreach ($this->timeSlots as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="notes" label="Notes (optional)" rows="2" />

            <div class="flex gap-3">
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Add event' }}</flux:button>
                @if ($editingId)
                    <flux:button type="button" wire:click="resetForm" variant="subtle">Cancel</flux:button>
                @endif
            </div>
        </form>
    </div>
</div>
