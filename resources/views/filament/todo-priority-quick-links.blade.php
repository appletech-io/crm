@auth
    @php
        $outstandingCounts = \App\Models\TodoItem::query()
            ->ownedByCurrentUser()
            ->whereNull('completed_at')
            ->whereIn('priority', [\App\Enums\TodoPriority::Medium, \App\Enums\TodoPriority::Low])
            ->selectRaw('priority, count(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $indexUrl = \App\Filament\Resources\TodoItems\TodoItemResource::getUrl('index');
    @endphp

    @foreach ([\App\Enums\TodoPriority::Medium, \App\Enums\TodoPriority::Low] as $priority)
        <x-filament::icon-button
            tag="a"
            :href="$indexUrl . '?tab=' . $priority->value"
            :icon="$priority === \App\Enums\TodoPriority::Medium ? 'heroicon-o-exclamation-circle' : 'heroicon-o-minus-circle'"
            :color="$priority->color()"
            :badge="$outstandingCounts[$priority->value] ?? null"
            :badge-color="$priority->color()"
            :label="$priority->label() . ' priority to-dos'"
            :tooltip="$priority->label() . ' priority to-dos'"
        />
    @endforeach
@endauth
