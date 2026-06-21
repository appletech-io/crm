<?php

namespace App\Filament\Resources\EducationClients\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EducationClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                    ]),

                Section::make('Education Specific Information')
                    ->schema([
                        TextInput::make('subject')
                            ->required(),
                        TextInput::make('grade_level'),
                        TextInput::make('notes'),
                    ]),
            ]);
    }
}
