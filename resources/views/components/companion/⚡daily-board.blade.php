<?php

use App\Models\CompanionEvent;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.companion', ['title' => 'Today'])] class extends Component
{
    #[Computed]
    public function today(): Carbon
    {
        return Carbon::today();
    }

    #[Computed]
    public function tomorrow(): Carbon
    {
        return Carbon::tomorrow();
    }

    /**
     * "Morning" / "Afternoon" / "Evening" / "Night" — the single biggest
     * thing dementia-friendly displays add over a plain clock, so someone
     * doesn't have to interpret an "AM/PM" or a raw hour.
     */
    #[Computed]
    public function partOfDay(): string
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour < 6 => 'Night',
            $hour < 12 => 'Morning',
            $hour < 17 => 'Afternoon',
            $hour < 21 => 'Evening',
            default => 'Night',
        };
    }

    #[Computed]
    public function todaysEvents()
    {
        return CompanionEvent::whereDate('date', $this->today)
            ->orderByRaw('time IS NULL, time asc')
            ->get();
    }

    #[Computed]
    public function tomorrowsEvents()
    {
        return CompanionEvent::whereDate('date', $this->tomorrow)
            ->orderByRaw('time IS NULL, time asc')
            ->get();
    }

    /**
     * The event happening right now, if any — a fixed-length window either
     * side of "now" so something is highlighted rather than nothing.
     */
    #[Computed]
    public function currentEventId(): ?int
    {
        $now = now()->format('H:i:s');

        return $this->todaysEvents
            ->first(function ($event) use ($now) {
                if (! $event->time) {
                    return false;
                }

                $start = $event->time->format('H:i:s');
                $end = $event->time->copy()->addMinutes(30)->format('H:i:s');

                return $now >= $start && $now < $end;
            })?->id;
    }
};

?>

<div wire:poll.60s class="flex min-h-screen flex-col items-center gap-10 px-8 py-12 text-center">
    <div>
        <p class="text-4xl font-semibold text-amber-600 sm:text-5xl">{{ $this->partOfDay }}</p>
        <h1 class="mt-2 text-6xl font-bold tracking-tight text-neutral-900 sm:text-8xl">{{ $this->today->format('l') }}</h1>
        <p class="mt-3 text-3xl text-neutral-500 sm:text-4xl">{{ $this->today->format('j F Y') }}</p>
        <p class="mt-6 text-5xl font-mono font-semibold text-neutral-900 sm:text-7xl" x-data x-init="
            $el.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
            setInterval(() => { $el.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'}); }, 1000);
        "></p>
    </div>

    <div class="w-full max-w-3xl rounded-3xl border border-neutral-200 bg-white p-8 text-left shadow-sm">
        <h2 class="text-3xl font-semibold text-amber-600 sm:text-4xl">Today</h2>

        @if ($this->todaysEvents->isEmpty())
            <p class="mt-6 text-3xl text-neutral-400">Nothing planned today.</p>
        @else
            <ul class="mt-6 flex flex-col gap-5">
                @foreach ($this->todaysEvents as $event)
                    <li @class([
                        'flex items-baseline gap-6 rounded-2xl px-6 py-5 text-3xl text-neutral-900 sm:text-4xl',
                        'bg-amber-400 font-bold' => $event->id === $this->currentEventId,
                        'bg-neutral-50' => $event->id !== $this->currentEventId,
                    ])>
                        <span class="w-40 shrink-0 font-mono">{{ $event->display_time }}</span>
                        <span>{{ $event->title }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="w-full max-w-3xl rounded-3xl border border-neutral-200 bg-white p-8 text-left shadow-sm">
        <h2 class="text-3xl font-semibold text-neutral-500 sm:text-4xl">Tomorrow — {{ $this->tomorrow->format('l') }}</h2>

        @if ($this->tomorrowsEvents->isEmpty())
            <p class="mt-6 text-2xl text-neutral-400">Nothing planned yet.</p>
        @else
            <ul class="mt-6 flex flex-col gap-4">
                @foreach ($this->tomorrowsEvents as $event)
                    <li class="flex items-baseline gap-6 text-2xl text-neutral-600 sm:text-3xl">
                        <span class="w-40 shrink-0 font-mono">{{ $event->display_time }}</span>
                        <span>{{ $event->title }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
