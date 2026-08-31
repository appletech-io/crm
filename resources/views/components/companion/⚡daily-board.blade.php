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

<div wire:poll.60s class="flex h-screen flex-col gap-6 overflow-hidden px-8 py-6 text-center">
    <div class="shrink-0">
        <p class="text-3xl font-bold tracking-tight text-neutral-900 sm:text-4xl">
            {{ $this->today->format('l jS') }} - <span class="font-mono" x-data x-init="
                $el.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', hour12: false});
                setInterval(() => { $el.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', hour12: false}); }, 1000);
            "></span>
        </p>
    </div>

    <div class="grid min-h-0 flex-1 grid-cols-2 gap-6">
        <div class="flex min-h-0 flex-col rounded-3xl border border-neutral-200 bg-white p-6 text-left shadow-sm">
            <h2 class="shrink-0 text-2xl font-semibold text-amber-600 sm:text-3xl">Today</h2>

            <div class="mt-4 min-h-0 flex-1 overflow-y-auto">
                @if ($this->todaysEvents->isEmpty())
                    <p class="text-2xl text-neutral-400">Nothing planned today.</p>
                @else
                    <ul class="flex flex-col gap-3">
                        @foreach ($this->todaysEvents as $event)
                            <li @class([
                                'flex items-baseline gap-4 rounded-2xl px-5 py-4 text-xl text-neutral-900 sm:text-2xl',
                                'bg-amber-400 font-bold' => $event->id === $this->currentEventId,
                                'bg-neutral-50' => $event->id !== $this->currentEventId,
                            ])>
                                <span class="w-28 shrink-0 font-mono">{{ $event->display_time }}</span>
                                <span>{{ $event->title }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="flex min-h-0 flex-col rounded-3xl border border-neutral-200 bg-white p-6 text-left shadow-sm">
            <h2 class="shrink-0 text-2xl font-semibold text-neutral-500 sm:text-3xl">Tomorrow — {{ $this->tomorrow->format('l') }}</h2>

            <div class="mt-4 min-h-0 flex-1 overflow-y-auto">
                @if ($this->tomorrowsEvents->isEmpty())
                    <p class="text-xl text-neutral-400">Nothing planned yet.</p>
                @else
                    <ul class="flex flex-col gap-3">
                        @foreach ($this->tomorrowsEvents as $event)
                            <li class="flex items-baseline gap-4 rounded-2xl bg-neutral-50 px-5 py-4 text-xl text-neutral-600 sm:text-2xl">
                                <span class="w-28 shrink-0 font-mono">{{ $event->display_time }}</span>
                                <span>{{ $event->title }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
