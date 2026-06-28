<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->dehydrateStateUsing(fn (string $state): string => bcrypt($state))
                            ->helperText('Leave blank to keep the current password.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Roles')
                    ->schema([
                        Select::make('roles')
                            ->multiple()
                            ->relationship('roles', 'name')
                            ->preload(),
                    ]),

                Section::make('Sectors')
                    ->schema([
                        Select::make('industries')
                            ->label('Sectors')
                            ->multiple()
                            ->relationship(
                                name: 'industries',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query, ?Model $record) => $query
                                    ->whereIn('industries.id', function ($sub) use ($record) {
                                        $sub->select('industry_id')
                                            ->from('company_industry')
                                            ->where('company_id', $record?->company_id ?? auth()->user()->company_id);
                                    }),
                            )
                            ->preload(),
                    ]),
            ]);
    }
}
