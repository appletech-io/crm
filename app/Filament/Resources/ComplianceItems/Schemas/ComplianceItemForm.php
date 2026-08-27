<?php

namespace App\Filament\Resources\ComplianceItems\Schemas;

use App\Enums\ComplianceItemDataType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ComplianceItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            Textarea::make('description')
                ->label('Description')
                ->helperText('Shown to the candidate as help text when they fill this in.')
                ->rows(3),

            Repeater::make('fields')
                ->relationship()
                ->hiddenLabel()
                ->schema([
                    TextInput::make('name')
                        ->label('Display Text')
                        ->helperText('E.g. "DBS Number", "Issue Date", "Expiry Date".')
                        ->required()
                        ->maxLength(255),

                    Select::make('data_type')
                        ->label('Data Type')
                        ->options(ComplianceItemDataType::options())
                        ->native(false)
                        ->required(),

                    Textarea::make('description')
                        ->label('Description')
                        ->helperText('Shown to the candidate as help text for this field specifically.')
                        ->rows(2),
                ])
                ->columns(2)
                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                ->collapsible()
                ->collapsed()
                ->addActionLabel('Add Field')
                ->columnSpanFull()
                ->default([]),
        ]);
    }
}
