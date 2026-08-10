<?php

namespace App\Filament\Resources\TodoItems\Schemas;

use App\Enums\TodoPriority;
use App\Filament\Support\TodoLinkedRecord;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Industry;
use App\Models\TodoItem;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
                // Editable when first creating a to-do by hand, but most
                // to-dos are created by an automation and shouldn't have
                // their linked record reassigned afterwards.
                ->disabled(fn (?TodoItem $record): bool => $record !== null)
                ->dehydrated()
                // A reference/application/vacancy isn't one of the types()
                // above (this field can only ever select what a person would
                // manually link), so for those it would otherwise render
                // blank instead of showing what it's actually linked to —
                // the TextEntry link below covers every linkable type instead.
                ->visible(fn (?TodoItem $record): bool => $record === null || self::isDirectlySelectableType($record->model))
                ->columnSpanFull(),

            TextEntry::make('linked_record_link_1')
                ->hiddenLabel()
                ->state(fn (?TodoItem $record): ?string => self::linkedRecordLinks($record)[0]['label'] ?? null)
                ->url(fn (?TodoItem $record): ?string => self::linkedRecordLinks($record)[0]['url'] ?? null)
                ->openUrlInNewTab()
                ->visible(fn (?TodoItem $record): bool => isset(self::linkedRecordLinks($record)[0]))
                ->columnSpanFull(),

            TextEntry::make('linked_record_link_2')
                ->hiddenLabel()
                ->state(fn (?TodoItem $record): ?string => self::linkedRecordLinks($record)[1]['label'] ?? null)
                ->url(fn (?TodoItem $record): ?string => self::linkedRecordLinks($record)[1]['url'] ?? null)
                ->openUrlInNewTab()
                ->visible(fn (?TodoItem $record): bool => isset(self::linkedRecordLinks($record)[1]))
                ->columnSpanFull(),

            TextEntry::make('linked_record_link_3')
                ->hiddenLabel()
                ->state(fn (?TodoItem $record): ?string => self::linkedRecordLinks($record)[2]['label'] ?? null)
                ->url(fn (?TodoItem $record): ?string => self::linkedRecordLinks($record)[2]['url'] ?? null)
                ->openUrlInNewTab()
                ->visible(fn (?TodoItem $record): bool => isset(self::linkedRecordLinks($record)[2]))
                ->columnSpanFull(),
        ]);
    }

    /** @return array<int, array{label: string, url: string}> */
    private static function linkedRecordLinks(?TodoItem $record): array
    {
        return $record ? TodoLinkedRecord::links($record->model) : [];
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

    private static function isDirectlySelectableType(?Model $model): bool
    {
        if ($model === null) {
            return true;
        }

        $candidateModelClass = Industry::candidateModelForSlug(active_industry() ?? '');

        return $model instanceof Client
            || $model instanceof Booking
            || ($candidateModelClass && $model instanceof $candidateModelClass);
    }
}
