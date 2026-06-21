<?php

namespace App\Filament\Resources\EmailTemplates\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('subject')
                            ->required()
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Content')
                    ->schema([
                        RichEditor::make('body')
                            ->required()
                            ->columnSpanFull(),
                        TagsInput::make('placeholders')
                            ->placeholder('Add placeholders...'),
                    ]),
            ]);
    }
}
