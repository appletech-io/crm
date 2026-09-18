<?php

namespace App\Filament\Resources\UserQuickLinks\Schemas;

use App\Enums\QuickLinkIcon;
use App\Filament\Support\QuickLinkCatalog;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class UserQuickLinkForm
{
    public const LABEL_MAX_LENGTH = 40;

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('quick_pick')
                ->label('Choose a page from this CRM')
                ->helperText('Picks a label, icon and link for you — or leave this blank and fill the fields in below yourself.')
                ->options(fn (): array => collect(QuickLinkCatalog::availableForCurrentUser())->pluck('label', 'url')->all())
                ->searchable()
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(function (Set $set, ?string $state) {
                    $match = collect(QuickLinkCatalog::availableForCurrentUser())->firstWhere('url', $state);

                    if (! $match) {
                        return;
                    }

                    $set('label', $match['label']);
                    $set('icon', $match['icon']);
                    $set('url', $match['url']);
                })
                ->columnSpanFull(),

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
                ->helperText('Or paste any page URL from this CRM, e.g. from your browser\'s address bar.')
                ->required()
                ->maxLength(255),
        ]);
    }
}
