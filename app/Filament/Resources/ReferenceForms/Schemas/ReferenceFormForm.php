<?php

namespace App\Filament\Resources\ReferenceForms\Schemas;

use App\Enums\ReferenceFieldType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ReferenceFormForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            Textarea::make('description')
                ->label('Description')
                ->helperText('Shown to staff when choosing a reference form — not seen by the referee.')
                ->rows(2),

            Toggle::make('is_statement_only')
                ->label('Statement Only')
                ->helperText('No questions are sent to a referee — used for things like a candidate\'s own gap-in-employment statement.')
                ->live(),

            Toggle::make('needs_position_and_organisation')
                ->label('Ask Referee to Confirm Position & Organisation')
                ->default(true)
                ->visible(fn (Get $get): bool => ! $get('is_statement_only')),

            Repeater::make('fields')
                ->relationship()
                ->hiddenLabel()
                ->visible(fn (Get $get): bool => ! $get('is_statement_only'))
                ->schema([
                    TextInput::make('label')
                        ->label('Question')
                        ->helperText('Use :company_name to insert the agency\'s name, e.g. "Please inform :company_name of any concerns."')
                        ->required()
                        ->live(onBlur: true)
                        ->maxLength(255),

                    Select::make('field_type')
                        ->label('Answer Type')
                        ->options(ReferenceFieldType::options())
                        ->native(false)
                        ->required()
                        ->live(),

                    TagsInput::make('options')
                        ->label('Choices')
                        ->helperText('The choices a referee can pick from, e.g. Yes, No, N/A.')
                        ->visible(fn (Get $get): bool => $get('field_type') === 'radio')
                        ->required(fn (Get $get): bool => $get('field_type') === 'radio')
                        ->columnSpanFull(),

                    Toggle::make('required')
                        ->default(true),

                    TextInput::make('section_heading')
                        ->label('Section Heading')
                        ->helperText('Consecutive questions sharing the same heading are grouped together. Leave blank for no heading.')
                        ->maxLength(255),

                    Select::make('show_when_field_key')
                        ->label('Only Show When...')
                        ->helperText('Leave blank to always show this question.')
                        ->native(false)
                        ->options(function (Get $get): array {
                            $currentLabel = $get('label');

                            return collect($get('../../fields') ?? [])
                                ->filter(fn (array $sibling): bool => filled($sibling['label'] ?? null) && $sibling['label'] !== $currentLabel)
                                ->mapWithKeys(fn (array $sibling): array => [
                                    Str::slug($sibling['label'], '_') => $sibling['label'],
                                ])
                                ->all();
                        }),

                    TextInput::make('show_when_value')
                        ->label('...Is Answered')
                        ->helperText('The exact choice that reveals this question, e.g. "Yes".')
                        ->visible(fn (Get $get): bool => filled($get('show_when_field_key'))),
                ])
                ->columns(2)
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->collapsible()
                ->collapsed()
                ->reorderableWithButtons()
                ->orderColumn('sort_order')
                ->addActionLabel('Add Question')
                ->columnSpanFull()
                ->default([]),
        ]);
    }
}
