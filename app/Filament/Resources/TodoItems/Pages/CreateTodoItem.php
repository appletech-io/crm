<?php

namespace App\Filament\Resources\TodoItems\Pages;

use App\Filament\Resources\TodoItems\Schemas\TodoItemForm;
use App\Filament\Resources\TodoItems\TodoItemResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateTodoItem extends CreateRecord
{
    protected static string $resource = TodoItemResource::class;

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $this->form->fill($this->queryStringPrefillData());

        $this->callHook('afterFill');
    }

    /** @return array<string, mixed>|null */
    protected function queryStringPrefillData(): ?array
    {
        $modelType = request()->query('model_type');
        $modelId = request()->query('model_id');
        $name = request()->query('name');

        if (blank($modelType) || blank($modelId)) {
            return null;
        }

        return [
            'model_type' => $modelType,
            'model_id' => $modelId,
            'name' => filled($name) ? Str::limit($name, TodoItemForm::NAME_MAX_LENGTH, '') : null,
        ];
    }
}
