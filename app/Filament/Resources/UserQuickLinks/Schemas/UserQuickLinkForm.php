<?php

namespace App\Filament\Resources\UserQuickLinks\Schemas;

use App\Enums\QuickLinkIcon;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserQuickLinkForm
{
    public const LABEL_MAX_LENGTH = 40;

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')
                ->required()
                ->maxLength(self::LABEL_MAX_LENGTH),

            Select::make('icon')
                ->label('Icon')
                ->options(QuickLinkIcon::options())
                ->searchable()
                ->required(),

            TextInput::make('url')
                ->label('Link')
                ->helperText('Paste a page URL from this CRM, e.g. from your browser\'s address bar.')
                ->required()
                ->maxLength(255),
        ]);
    }
}
