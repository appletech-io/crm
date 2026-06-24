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
            @php $activities = $this->record?->activities()->with('user')->get() ?? collect(); @endphp

            @foreach ($activities as $activity)
                <div style="display:flex; gap:1rem; padding:0.75rem; border-bottom:1px solid rgba(255,255,255,0.1);">
                    <div style="width:5rem; flex-shrink:0;">
                        <x-filament::badge :color="match($activity->type) {
                    \App\Enums\ActivityType::Call  => 'success',
                    \App\Enums\ActivityType::Note  => 'info',
                    \App\Enums\ActivityType::Email => 'primary',
                    default                        => 'gray',
                }">{{ $activity->type->label() }}</x-filament::badge>
                    </div>
                    <div style="flex:1;">
                        <p style="font-size:0.875rem; color:#e5e7eb;">{{ $activity->note ?? $activity->body }}</p>
                    </div>
                    <div style="text-align:right; flex-shrink:0;">
                        <p style="font-size:0.875rem; color:#d1d5db;">{{ $activity->user?->name ?? 'System' }}</p>
                        <p style="font-size:0.75rem; color:#6b7280;">{{ $activity->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @endforeach

            <x-filament-actions::modals />
        </div>

    @endif

    <x-filament-actions::modals />
</div>
