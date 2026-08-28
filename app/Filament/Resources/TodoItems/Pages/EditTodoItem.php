<?php

namespace App\Filament\Resources\TodoItems\Pages;

use App\Filament\Resources\TodoItems\TodoItemResource;
use Filament\Resources\Pages\EditRecord;

class EditTodoItem extends EditRecord
{
    protected static string $resource = TodoItemResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
