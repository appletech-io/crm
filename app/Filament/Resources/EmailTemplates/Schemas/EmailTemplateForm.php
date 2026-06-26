<?php

namespace App\Filament\Resources\EmailTemplates\Schemas;

use App\Enums\EmailTemplateType;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
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
                        Select::make('type')
                            ->options(collect(EmailTemplateType::cases())
                                ->mapWithKeys(fn (EmailTemplateType $t) => [$t->value => $t->label()])
                                ->toArray()
                            )
                            ->default(EmailTemplateType::General->value)
                            ->required(),
                        TextInput::make('subject')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Content')
                    ->schema([
                        RichEditor::make('body')
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Available placeholders: {firstname}, {lastname}, {email}, {application_link}, {expiry_date}'),
                        TagsInput::make('placeholders')
                            ->placeholder('Add placeholders...'),
                    ]),
            ]);
    }
}
