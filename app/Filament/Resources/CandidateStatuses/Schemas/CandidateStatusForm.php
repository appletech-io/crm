<?php

namespace App\Filament\Resources\CandidateStatuses\Schemas;

use App\Models\CandidateStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

            Toggle::make('is_filled_status')
                ->label('Counts as a filled placement')
                ->helperText('Once every position on a vacancy is placed with candidates in a status like this, its "Placements Filled" condition becomes true — usable in Job Status Automations to move the vacancy on.'),
        ]);
    }
}
