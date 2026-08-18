<?php

namespace App\Filament\Resources\ClientContactJobTitles\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClientContactJobTitleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
        ]);
    }
}
