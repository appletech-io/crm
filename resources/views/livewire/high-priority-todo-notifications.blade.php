@php
    $todos = $this->todos;
    $canViewTodos = \App\Filament\Resources\TodoItems\TodoItemResource::canViewAny();
@endphp

<div>
    @if ($todos->isNotEmpty() || $canViewTodos)
        <x-filament::dropdown placement="bottom-end" teleport>
            <x-slot name="trigger">
                <x-filament::icon-button
                    :badge="$todos->count() ?: null"
                    badge-color="danger"
                    badge-size="md"
                    color="gray"
                    icon="heroicon-o-bell"
                    icon-size="lg"
                    label="High priority to-dos"
                    class="fi-topbar-high-priority-todos-btn"
                />
            </x-slot>

            <x-filament::dropdown.header icon="heroicon-o-bell" color="danger">
                High Priority To-Dos
            </x-filament::dropdown.header>

            @if ($todos->isEmpty())
                <div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                    No high priority to-dos outstanding.
                </div>
            @else
                <x-filament::dropdown.list>
                    @foreach ($todos as $todo)
                        <x-filament::dropdown.list.item
                            tag="a"
                            :href="\App\Filament\Resources\TodoItems\TodoItemResource::getUrl('edit', ['record' => $todo])"
                            icon="heroicon-o-exclamation-circle"
                            color="danger"
                        >
                            {{ \Illuminate\Support\Str::limit($todo->name, \App\Filament\Resources\TodoItems\Schemas\TodoItemForm::NAME_MAX_LENGTH) }}
                        </x-filament::dropdown.list.item>
                    @endforeach
                </x-filament::dropdown.list>
            @endif
        </x-filament::dropdown>
    @endif
</div>
