<?php

namespace App\Filament\Resources\TodoItems\Schemas;

use App\Enums\TodoPriority;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Industry;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class TodoItemForm
{
    public const NAME_MAX_LENGTH = 100;

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(self::NAME_MAX_LENGTH)
                ->columnSpanFull(),

            Textarea::make('description')
                ->rows(3)
                ->columnSpanFull(),

            Select::make('priority')
                ->options(TodoPriority::options())
                ->default(TodoPriority::Medium->value)
                ->required(),

            DateTimePicker::make('completed_at'),

            MorphToSelect::make('model')
                ->label('Link To')
                ->types(static::linkableTypes())
                ->searchable()
                ->preload()
                ->columnSpanFull(),
        ]);
    }

    /** @return array<int, Type> */
    protected static function linkableTypes(): array
    {
        $candidateModelClass = Industry::candidateModelForSlug(active_industry() ?? '');

        return array_values(array_filter([
            Type::make(Client::class)
                ->titleAttribute('name')
                ->modifyOptionsQueryUsing(fn (Builder $query): Builder => $query->where('industry_id', active_industry_id())),

            $candidateModelClass ? Type::make($candidateModelClass)
                ->label('Candidate')
                ->titleAttribute('first_name')
                ->getOptionLabelFromRecordUsing(fn ($record): string => trim("{$record->first_name} {$record->last_name}"))
                ->modifyOptionsQueryUsing(fn (Builder $query): Builder => $query->visibleToCurrentUser())
                : null,

            $candidateModelClass ? Type::make(Booking::class)
                ->label('Booking')
                ->titleAttribute('id')
                ->getOptionLabelFromRecordUsing(fn (Booking $record): string => "Booking #{$record->id}".($record->client ? " — {$record->client->name}" : ''))
                ->modifyOptionsQueryUsing(fn (Builder $query): Builder => $query->where('candidate_type', $candidateModelClass)->visibleToCurrentUser())
                : null,
        ]));
    }
}
