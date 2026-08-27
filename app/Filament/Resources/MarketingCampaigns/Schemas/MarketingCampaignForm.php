<?php

namespace App\Filament\Resources\MarketingCampaigns\Schemas;

use App\Models\ClientContactJobTitle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MarketingCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('client_job_titles')
                    ->label('Client Job Titles')
                    ->helperText('Send to every contact at each client holding one of these job titles, instead of just the booking contact. Leave blank to keep using the booking contact.')
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => ClientContactJobTitle::query()
                        ->where('company_id', auth()->user()->company_id)
                        ->where('industry_id', active_industry_id())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->columnSpanFull(),
            ]);
    }
}
