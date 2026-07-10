<?php

namespace App\Filament\Resources\CandidateStatuses\Schemas;

use App\Models\CandidateStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CandidateStatusForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            Select::make('color')
                ->options(CandidateStatus::COLOR_OPTIONS)
                ->required(),
        ]);
    }
}
