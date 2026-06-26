<div class="space-y-4">

    {{-- Header --}}
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        {{ $this->logActivityAction }}
    </div>

    @php $activities = $this->record?->activities()->with('user')->get() ?? collect(); @endphp

    @if ($activities->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-200 dark:border-white/10 px-4 py-12 text-center">
            <p class="text-sm text-gray-400 dark:text-gray-500">No activity recorded yet.</p>
        </div>
    @else

        <div>
            @foreach ($activities as $activity)
                <div
                    wire:click="mountAction('viewActivity', { activity: {{ $activity->id }} })"
                    style="display:flex; gap:1rem; padding:0.75rem; border-bottom:1px solid color-mix(in srgb, currentColor 15%, transparent); cursor:pointer;"
                    class="transition hover:bg-gray-50 dark:hover:bg-white/5"
                >
                    <div style="width:5rem; flex-shrink:0;">
                        <x-filament::badge :color="$activity->type->color()">{{ $activity->type->label() }}</x-filament::badge>
                    </div>
                    <div style="flex:1;">
                        <p class="text-sm text-gray-900 dark:text-gray-100">{{ $activity->note ?? $activity->body }}</p>
                    </div>
                    <div style="text-align:right; flex-shrink:0;">
                        <p class="text-sm text-gray-900 dark:text-gray-100">{{ $activity->user?->name ?? 'System' }}</p>
                        <p class="text-sm text-gray-900 dark:text-gray-100">{{ $activity->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @endforeach

            <x-filament-actions::modals />
        </div>

    @endif

    <x-filament-actions::modals />
</div>
