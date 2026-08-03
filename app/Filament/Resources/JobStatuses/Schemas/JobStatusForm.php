<?php

namespace App\Filament\Resources\JobStatuses\Schemas;

use App\Models\JobStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class JobStatusForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            Select::make('color')
                ->options(JobStatus::COLOR_OPTIONS)
                ->required(),
        ]);
    }
}
