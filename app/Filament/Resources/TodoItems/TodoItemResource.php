<?php

namespace App\Filament\Resources\TodoItems;

use App\Filament\Resources\TodoItems\Pages\CreateTodoItem;
use App\Filament\Resources\TodoItems\Pages\EditTodoItem;
use App\Filament\Resources\TodoItems\Pages\ListTodoItems;
use App\Filament\Resources\TodoItems\Schemas\TodoItemForm;
use App\Filament\Resources\TodoItems\Tables\TodoItemsTable;
use App\Models\TodoItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TodoItemResource extends Resource
{
    protected static ?string $model = TodoItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static ?string $navigationLabel = 'My To-Dos';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $pluralModelLabel = 'To-Dos';

    protected static ?string $modelLabel = 'To-Do';

    public static function canViewAny(): bool
    {
        return ! (auth()->user()?->hasRole('site_admin') ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return TodoItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TodoItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTodoItems::route('/'),
            'create' => CreateTodoItem::route('/create'),
            'edit' => EditTodoItem::route('/{record}/edit'),
        ];
    }
}
