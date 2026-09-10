<?php

namespace App\Filament\Resources\ClientPools\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClientPoolForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }
}
