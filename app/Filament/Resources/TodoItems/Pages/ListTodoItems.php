<?php

namespace App\Filament\Resources\TodoItems\Pages;

use App\Enums\TodoPriority;
use App\Filament\Resources\TodoItems\TodoItemResource;
use App\Models\TodoItem;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTodoItems extends ListRecords
{
    protected static string $resource = TodoItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * One tab per priority, badged with how many of the user's own to-dos
     * are still outstanding in that priority — mirrors the count-per-tab
     * pattern (e.g. Filament's own demo Orders page) rather than one long
     * table with a priority column to scan.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge($this->outstandingCount()),
        ] + collect(TodoPriority::cases())
            ->mapWithKeys(fn (TodoPriority $priority): array => [
                $priority->value => Tab::make($priority->label())
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('priority', $priority))
                    ->badge($this->outstandingCount($priority))
                    ->badgeColor($priority->color()),
            ])
            ->all();
    }

    private function outstandingCount(?TodoPriority $priority = null): int
    {
        return TodoItem::query()
            ->ownedByCurrentUser()
            ->whereNull('completed_at')
            ->when($priority, fn (Builder $query, TodoPriority $priority) => $query->where('priority', $priority))
            ->count();
    }
}
