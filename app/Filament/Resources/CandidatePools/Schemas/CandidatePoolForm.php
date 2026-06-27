<?php

namespace App\Filament\Resources\CandidatePools\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class CandidatePoolForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Toggle::make('company_pool')
                    ->label('Company Pool')
                    ->helperText('Visible to all consultants in this industry.')
                    ->visible(fn (): bool => Auth::user()?->hasAnyRole(['admin', 'site_admin']) ?? false),
            ]);
    }
}
