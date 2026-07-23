<?php

namespace App\Filament\Resources\Actions\Schemas;

use App\Enums\TodoPriority;
use App\Filament\Resources\TodoItems\Schemas\TodoItemForm;
use App\Filament\Support\ConditionsRepeaterField;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Industry;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ActionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Action Name')
                ->helperText('An internal label so you can recognise this automation in the list below.')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Select::make('model_type')
                ->label('Applies To')
                ->options(static::modelTypeOptions())
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('conditions', []))
                ->columnSpanFull(),

            ConditionsRepeaterField::make('conditions', fn (Get $get): array => static::suggestionsFor($get('/data.model_type'))),

            TextInput::make('todo_name')
                ->label('To-Do Name')
                ->required()
                ->maxLength(TodoItemForm::NAME_MAX_LENGTH)
                ->columnSpanFull(),

            Textarea::make('todo_description')
                ->label('To-Do Description')
                ->rows(3)
                ->columnSpanFull(),

            Select::make('todo_priority')
                ->label('To-Do Priority')
                ->options(TodoPriority::options())
                ->default(TodoPriority::Medium->value)
                ->required(),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    /** @return array<string, string> */
    public static function modelTypeOptions(): array
    {
        $options = [
            Client::class => 'Client',
            Booking::class => 'Booking',
        ];

        $candidateModelClass = Industry::candidateModelForSlug(active_industry() ?? '');

        if ($candidateModelClass) {
            $options[$candidateModelClass] = 'Candidate';
        }

        return $options;
    }

    /** @return array<string, array{label: string, type: string}> */
    public static function suggestionsFor(?string $modelType): array
    {
        if (! $modelType || ! method_exists($modelType, 'candidateFieldSuggestions')) {
            return [];
        }

        return $modelType::candidateFieldSuggestions();
    }
}
