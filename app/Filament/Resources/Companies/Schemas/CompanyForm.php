<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Enums\EmailProvider;
use App\Models\Industry;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Company Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->columnSpanFull(),
                        Select::make('industries')
                            ->relationship('industries', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->label('Sectors')
                            ->columnSpanFull()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->live(onBlur: true),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                return Industry::create([
                                    'name' => $data['name'],
                                    'slug' => Str::slug($data['name']),
                                ])->getKey();
                            })
                            ->editOptionForm([
                                TextInput::make('name')
                                    ->required(),
                            ]),
                    ]),

                Section::make('Email Settings')
                    ->schema([
                        Select::make('email_provider')
                            ->label('Email Provider')
                            ->options(EmailProvider::options())
                            ->default(EmailProvider::Microsoft->value)
                            ->required(),
                    ]),
            ]);
    }
}
