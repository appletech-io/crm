<?php

namespace App\Filament\Resources\EmailTemplates\Schemas;

use App\Enums\EmailTemplateAudience;
use App\Enums\EmailTemplateType;
use App\Services\Mail\CustomTemplatePlaceholders;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
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
                            ->required()
                            ->live(),
                        Select::make('audience')
                            ->options(EmailTemplateAudience::options())
                            ->live()
                            ->visible(fn (Get $get): bool => $get('type') === EmailTemplateType::Custom->value)
                            ->required(fn (Get $get): bool => $get('type') === EmailTemplateType::Custom->value),
                        TextInput::make('subject')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Content')
                    ->schema([
                        TextEntry::make('available_placeholders')
                            ->hiddenLabel()
                            ->state(fn (Get $get): HtmlString => static::placeholdersList($get('type'), $get('audience')))
                            ->columnSpanFull(),

                        RichEditor::make('body')
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Use any of the placeholders above — they\'ll be swapped for the real values when the email is sent.'),
                    ]),
            ]);
    }

    private static function placeholdersList(?string $type, ?string $audience): HtmlString
    {
        $placeholders = $type === EmailTemplateType::Custom->value
            ? CustomTemplatePlaceholders::definitions(EmailTemplateAudience::tryFrom($audience ?? '') ?? EmailTemplateAudience::Both)
            : EmailTemplateType::tryFrom($type ?? '')?->placeholders() ?? [];

        if (empty($placeholders)) {
            return new HtmlString(
                '<p class="text-sm text-gray-500 dark:text-gray-400">This template type has no placeholders available.</p>'
            );
        }

        $items = collect($placeholders)
            ->map(fn (string $description, string $key): string => sprintf(
                '<li><code>{%s}</code> — %s</li>',
                e($key),
                e($description),
            ))
            ->implode('');

        return new HtmlString(
            '<p class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Available placeholders</p>'
            .'<ul class="list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-400">'.$items.'</ul>'
        );
    }
}
